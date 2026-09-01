<?php

namespace Nawasara\Proxmox\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Nawasara\Alerting\Facades\Alerter;
use Nawasara\Alerting\Models\AlertRule;
use Nawasara\Proxmox\Models\ProxmoxNode;
use Nawasara\Proxmox\Models\ProxmoxVm;

/**
 * Memperingatkan sebelum disk (atau memori) habis.
 *
 * ## Kenapa ada
 *
 * Basis data produksi pernah MATI karena disknya penuh, dan tidak ada satu pun
 * peringatan sebelumnya. Datanya sebenarnya sudah ada — `disk_used` dan
 * `disk_total` disinkronkan tiap 15 menit — tetapi tidak ada yang membacanya
 * dan berkata "ini akan menjadi masalah".
 *
 * Disk penuh tidak memperlambat basis data, ia menghentikannya. Dan
 * pemulihannya jauh lebih mahal daripada menghapus berkas seminggu sebelumnya.
 *
 * ## ⚠️ Yang TIDAK dapat dilihat dari sini
 *
 * Proxmox hanya mengetahui pemakaian disk **di dalam** mesin untuk **LXC**.
 * Untuk **QEMU** (VM penuh), `disk` pada API bernilai **0** kecuali
 * qemu-guest-agent terpasang — dan di Ponorogo, **20 dari 20 QEMU melaporkan
 * 0** (diperiksa 2 September 2026).
 *
 * Karena itu VM QEMU **dilewati, bukan dianggap 0%**. Menganggapnya 0% jauh
 * lebih berbahaya daripada tidak memantaunya: dasbor akan tampak hijau untuk
 * mesin yang sebenarnya tidak diketahui keadaannya, dan itu persis rasa aman
 * palsu yang membuat kejadian kemarin tidak tertangkap.
 *
 * Jumlah yang dilewati dicatat di log tiap putaran supaya keterbatasan ini
 * tetap terlihat, bukan terlupakan.
 *
 * Mesin yang paling penting — Server-DB-Production, Docker-Master,
 * docker-keycloak — semuanya LXC, jadi terpantau.
 */
class CheckCapacityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function handle(): void
    {
        if (! class_exists(Alerter::class)) {
            return;   // alerting opsional saat pemasangan
        }

        $this->registerRules();

        $t = config('nawasara-proxmox.thresholds');

        $dilewati = 0;

        foreach (ProxmoxNode::all() as $node) {
            $this->periksa(
                key: 'proxmox.node.disk',
                label: "Node {$node->node_name}",
                target: 'ProxmoxNode',
                targetId: $node->node_name,
                used: (int) $node->disk_used,
                total: (int) $node->disk_total,
                warning: $t['disk_warning'],
                critical: $t['disk_critical'],
                jenis: 'Disk',
            );

            $this->periksa(
                key: 'proxmox.node.memory',
                label: "Node {$node->node_name}",
                target: 'ProxmoxNode',
                targetId: $node->node_name,
                used: (int) $node->mem_used,
                total: (int) $node->mem_total,
                warning: $t['mem_warning'],
                critical: $t['mem_critical'],
                jenis: 'Memori',
            );
        }

        foreach (ProxmoxVm::where('status', 'running')->get() as $vm) {
            // QEMU tanpa guest-agent melaporkan 0 — dilewati, JANGAN dianggap
            // kosong. Lihat catatan kelas.
            if ((int) $vm->disk_total <= 0 || (int) $vm->disk_used <= 0) {
                $dilewati++;

                continue;
            }

            $this->periksa(
                key: 'proxmox.vm.disk',
                label: $vm->name ?: "VM {$vm->vmid}",
                target: 'ProxmoxVm',
                targetId: (string) $vm->vmid,
                used: (int) $vm->disk_used,
                total: (int) $vm->disk_total,
                warning: $t['disk_warning'],
                critical: $t['disk_critical'],
                jenis: 'Disk',
            );
        }

        if ($dilewati > 0) {
            Log::info("[proxmox] kapasitas: {$dilewati} mesin dilewati (disk tidak terbaca — QEMU tanpa guest-agent)");
        }
    }

    /**
     * Nyalakan peringatan bila melewati ambang, dan PULIHKAN bila sudah turun.
     *
     * Pemulihan otomatis itu yang membuat peringatan ini tetap dipercaya:
     * tanpanya, satu kali disk penuh akan menyala selamanya meski berkasnya
     * sudah dibersihkan sepuluh menit kemudian — dan lencana yang tidak pernah
     * padam berhenti dibaca orang.
     */
    protected function periksa(
        string $key,
        string $label,
        string $target,
        string $targetId,
        int $used,
        int $total,
        int $warning,
        int $critical,
        string $jenis,
    ): void {
        if ($total <= 0) {
            return;
        }

        $pct = round($used / $total * 100, 1);
        $sisaGb = round(($total - $used) / 1073741824, 1);
        $alerter = Alerter::class;

        // Persentase DAN sisa nyata harus sama-sama mengkhawatirkan.
        //
        // 84% dari 1 TB menyisakan 150 GB — tidak ada yang perlu dikerjakan
        // malam ini. Membunyikannya sama nyaring dengan 84% dari 20 GB (sisa
        // 3 GB) adalah cara tercepat membuat peringatan ini berhenti dibaca,
        // dan yang hilang justru yang sungguhan.
        //
        // Hanya berlaku untuk disk; memori tidak punya padanan yang masuk akal.
        $minFree = (int) config('nawasara-proxmox.thresholds.disk_min_free_gb', 20);

        if ($jenis === 'Disk' && $sisaGb > $minFree) {
            $alerter::resolve($key.'.warning', $target, $targetId);
            $alerter::resolve($key.'.critical', $target, $targetId);

            return;
        }

        // ⚠️ Severity diambil dari ATURAN, bukan dari konteks — jadi tingkat
        // gawat harus dipisah menjadi aturan sendiri.
        //
        // Menaruh 'severity' di konteks tidak berpengaruh sama sekali
        // (AlertEvaluator membacanya dari $rule->severity()), sehingga disk 95%
        // akan berbunyi sama seperti 80% — dan pemisahan yang paling penting,
        // antara "perhatikan" dan "kerjakan sekarang", hilang tanpa jejak.
        $gawat = $pct >= $critical;
        $keyDipakai = $gawat ? $key.'.critical' : $key.'.warning';

        $konteks = [
            'label' => $label,
            'jenis' => $jenis,
            'percent' => $pct,
            'used_gb' => round($used / 1073741824, 1),
            'total_gb' => round($total / 1073741824, 1),
            'sisa_gb' => $sisaGb,
            'threshold' => $gawat ? $critical : $warning,
        ];

        if ($pct < $warning) {
            // Sudah turun — padamkan KEDUA tingkat. Memadamkan yang sedang
            // menyala saja meninggalkan sisa: mesin yang sempat 95% lalu turun
            // ke 70% akan tetap menyala di tingkat gawat selamanya.
            $alerter::resolve($key.'.warning', $target, $targetId);
            $alerter::resolve($key.'.critical', $target, $targetId);

            return;
        }

        // Naik ke gawat: padamkan yang peringatan biasa supaya tidak ada dua
        // alert menyala untuk satu mesin yang sama.
        if ($gawat) {
            $alerter::resolve($key.'.warning', $target, $targetId);
        } else {
            $alerter::resolve($key.'.critical', $target, $targetId);
        }

        $alerter::fire($keyDipakai, $target, $targetId, $konteks);
    }

    /**
     * Aturan didaftarkan di sini, bukan di ServiceProvider.
     *
     * Job ini berjalan di pekerja antrean, dan daftar aturan hanya hidup di
     * memori proses. Pekerja yang tidak pernah mendaftarkan aturannya akan
     * melempar UnknownAlertRule saat memulihkan — jadi pendaftarannya harus
     * ikut di tempat yang sama dengan pemakaiannya.
     */
    protected function registerRules(): void
    {
        $alerter = Alerter::class;
        $rule = AlertRule::class;

        // Dua aturan per jenis: satu peringatan, satu gawat. Severity melekat
        // pada aturan, jadi inilah satu-satunya cara membedakan keduanya.
        $daftar = [
            'proxmox.node.disk.warning' => ['Disk node Proxmox mulai penuh', 'warning'],
            'proxmox.node.disk.critical' => ['Disk node Proxmox hampir habis', 'critical'],
            'proxmox.node.memory.warning' => ['Memori node Proxmox tinggi', 'warning'],
            'proxmox.node.memory.critical' => ['Memori node Proxmox hampir habis', 'critical'],
            'proxmox.vm.disk.warning' => ['Disk mesin mulai penuh', 'warning'],
            'proxmox.vm.disk.critical' => ['Disk mesin hampir habis', 'critical'],
        ];

        foreach ($daftar as $key => [$desc, $severity]) {
            if ($alerter::hasRule($key)) {
                continue;
            }

            $alerter::registerRule($rule::make([
                'key' => $key,
                'severity' => $severity,
                'category' => 'kapasitas',
                // Sekali sehari. Disk yang penuh tidak berubah keadaannya tiap
                // jam, dan mengingatkan tiap jam hanya membuat peringatannya
                // diabaikan justru saat paling penting.
                'cooldown_minutes' => 1440,
                'description' => $desc,
                'subject_template' => '{context.jenis} {context.label} {context.percent}% terpakai — sisa {context.sisa_gb} GB',
            ]));
        }
    }
}

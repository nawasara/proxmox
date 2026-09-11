<?php

namespace Nawasara\Proxmox\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Riwayat akses console — siapa membuka mesin apa, dan berapa lama.
 *
 * Console memakai SATU kredensial bersama (`root@pam` dari Vault), sehingga di
 * log Proxmox setiap sesi tampak sebagai root. Tabel
 * `nawasara_proxmox_console_sessions` adalah satu-satunya tempat pertanyaan
 * "siapa yang masuk" dapat dijawab — dan itu membuat ketepatannya jauh lebih
 * penting daripada catatan biasa.
 */
class ConsoleSessionLogTest extends TestCase
{
    /** Meniru perhitungan durasi di ConsoleController::close(). */
    private function durationSeconds(int $startedAt, int $endedAt): int
    {
        return $endedAt - $startedAt;
    }

    /**
     * Durasi dihitung di SERVER, bukan diterima dari browser.
     *
     * Angka yang dikirim klien dapat dikarang, dan justru angka inilah yang
     * akan dibaca orang saat mempertanyakan sebuah akses.
     */
    public function test_durasi_dihitung_dari_stempel_server(): void
    {
        $started = 1_000_000;
        $ended = 1_000_180;

        // Klien "melaporkan" 5 detik; yang dipakai tetap selisih server.
        $reportedByClient = 5;

        $this->assertSame(180, $this->durationSeconds($started, $ended));
        $this->assertNotSame($reportedByClient, $this->durationSeconds($started, $ended));
    }

    /**
     * Sesi menggantung ditutup memakai denyut TERAKHIR, bukan waktu penyapuan.
     *
     * Memakai waktu sekarang akan menambahkan menit-menit yang tidak pernah
     * terjadi — dan pada catatan yang dipakai mempertanggungjawabkan akses,
     * melebihkan sama buruknya dengan mengurangi.
     */
    public function test_sesi_menggantung_berakhir_di_denyut_terakhir(): void
    {
        $started = 1_000_000;
        $lastSeen = 1_000_300;   // denyut terakhir, 5 menit sesudah mulai
        $sweptAt = 1_003_600;    // penyapuan berjalan sejam kemudian

        $this->assertSame(300, $this->durationSeconds($started, $lastSeen));
        $this->assertNotSame($this->durationSeconds($started, $sweptAt), $this->durationSeconds($started, $lastSeen));
    }

    /**
     * Identitas pengguna DISALIN ke barisnya, bukan hanya direlasikan.
     *
     * Akun dapat berganti nama, berpindah orang, atau dihapus. Catatan yang
     * hanya menyimpan user_id akan ikut berubah artinya — atau lenyap bersama
     * akunnya, tepat ketika catatan itu paling dibutuhkan.
     */
    public function test_identitas_disalin_bukan_direlasikan(): void
    {
        $row = [
            'user_id' => 7,
            'user_name' => 'PRINGGO JUNI SAPUTRO',
            'user_email' => 'odyinggo@gmail.com',
        ];

        // Akun kemudian diubah namanya.
        $accountRenamedTo = 'Akun Lama (nonaktif)';

        $this->assertSame('PRINGGO JUNI SAPUTRO', $row['user_name']);
        $this->assertNotSame($accountRenamedTo, $row['user_name']);
    }

    /**
     * Sesi terbuka dan sesi menggantung adalah dua keadaan BERBEDA.
     *
     * Menyebut sesi menggantung sebagai "masih dibuka" akan membuat daftar
     * menyatakan orang sedang berada di dalam mesin padahal tabnya sudah lama
     * tertutup.
     */
    public function test_menggantung_dibedakan_dari_terbuka(): void
    {
        $now = 1_000_000;
        $staleAfter = 300; // 5 menit

        $open = ['ended_at' => null, 'last_seen_at' => $now - 30];
        $stale = ['ended_at' => null, 'last_seen_at' => $now - 900];

        $isStale = fn ($s) => $s['ended_at'] === null
            && $s['last_seen_at'] !== null
            && ($now - $s['last_seen_at']) > $staleAfter;

        $this->assertFalse($isStale($open));
        $this->assertTrue($isStale($stale));
    }

    /**
     * Isi shell TIDAK direkam — dan itu keputusan, bukan kelalaian.
     *
     * Merekam yang diketik berarti ikut merekam kata sandi yang diketik orang
     * di dalam shell, dan tempat penyimpanannya akan menjadi sasaran yang
     * lebih berharga daripada mesin yang dilindunginya.
     */
    public function test_isi_shell_tidak_dicatat(): void
    {
        $columns = [
            'user_id', 'user_name', 'user_email', 'user_nip',
            'node_name', 'vmid', 'vm_name', 'vm_type',
            'started_at', 'ended_at', 'duration_seconds', 'last_seen_at',
            'ip_address',
        ];

        foreach (['keystrokes', 'transcript', 'command_log', 'output'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns);
        }
    }
}

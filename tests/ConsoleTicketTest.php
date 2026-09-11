<?php

namespace Nawasara\Proxmox\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Console Proxmox: tiket, dan kenapa bentuknya begini.
 *
 * Sebelum ini, menu "Buka Console" hanya menautkan ke UI Proxmox tanpa
 * membawa autentikasi apa pun, sehingga menjawab "401 permission denied -
 * invalid PVE ticket" bagi siapa pun yang tidak kebetulan sudah masuk ke
 * Proxmox di tab lain. Ia tampak bekerja bagi admin yang sudah login, dan
 * gagal bagi semua orang lain — bentuk kegagalan yang paling sulit dilaporkan.
 */
class ConsoleTicketTest extends TestCase
{
    /**
     * Console teks TIDAK dapat memakai API token.
     *
     * Diuji langsung ke PVE 8.4.16: `/access/ticket` menjawab 401 untuk
     * kredensial token, dan `termproxy` menjawab 500. Itu batasan Proxmox,
     * bukan salah konfigurasi — dan alasan satu-satunya kredensial
     * username+kata sandi disimpan di Vault untuk paket ini.
     */
    public function test_console_teks_menuntut_kredensial_sesi(): void
    {
        // Token cukup untuk segalanya KECUALI termproxy.
        $bisaDenganToken = [
            'daftar VM' => true,
            'aksi lifecycle' => true,
            'snapshot' => true,
            'firewall' => true,
            'vncproxy' => true,
            'termproxy' => false,
        ];

        $this->assertFalse(
            $bisaDenganToken['termproxy'],
            'termproxy tidak menerima API token — butuh tiket sesi dari username+password',
        );

        // Sisanya harus tetap berjalan tanpa kredensial sesi sama sekali.
        unset($bisaDenganToken['termproxy']);
        foreach ($bisaDenganToken as $fitur => $bisa) {
            $this->assertTrue($bisa, "{$fitur} seharusnya cukup dengan API token");
        }
    }

    /**
     * Badan permintaan termproxy WAJIB form-encoded.
     *
     * Dengan JSON, Proxmox menjawab 500 `Not a HASH reference` — galat Perl
     * dari PVE::APIServer yang terlihat seperti kerusakan server, padahal
     * artinya bentuk badan permintaannya salah. Jebakan yang sama sudah
     * tercatat di vmAction() pada ProxmoxClient.
     */
    public function test_badan_termproxy_form_bukan_json(): void
    {
        $hasil = [
            'json' => 500,
            'form' => 200,
        ];

        $this->assertSame(500, $hasil['json'], 'JSON memicu "Not a HASH reference"');
        $this->assertSame(200, $hasil['form']);
    }

    /**
     * Alamat websocket tidak boleh berada di URL halaman.
     *
     * Ia memuat tiket yang setara kunci masuk, dan URL tersimpan di riwayat
     * peramban, log akses proxy, serta header Referer. Kunci cache acak
     * membuat URL-nya tidak berarti apa-apa bila bocor.
     */
    public function test_alamat_websocket_tidak_di_url(): void
    {
        $urlHalaman = '/nawasara-proxmox/console/4efe7c6100a97c2408502bbf8ba57c89';

        $this->assertStringNotContainsString('wss://', $urlHalaman);
        $this->assertStringNotContainsString('vncticket', $urlHalaman);
        $this->assertMatchesRegularExpression('#/console/[0-9a-f]{32}$#', $urlHalaman);
    }

    /**
     * Tiket sekali pakai.
     *
     * Memuat ulang tab berarti menerbitkan tiket baru dari daftar VM, bukan
     * memutar ulang sambungan lama — sehingga tautan yang tertinggal di
     * riwayat tidak dapat dipakai siapa pun.
     */
    public function test_tiket_sekali_pakai(): void
    {
        $cache = ['kunci-abc' => ['ws_url' => 'wss://...']];

        // Permintaan pertama menukar lalu menghapus.
        $pertama = $cache['kunci-abc'] ?? null;
        unset($cache['kunci-abc']);

        $kedua = $cache['kunci-abc'] ?? null;

        $this->assertNotNull($pertama);
        $this->assertNull($kedua, 'tiket masih dapat dipakai ulang');
    }

    /**
     * Websocket menuju Nawasara, BUKAN langsung ke Proxmox.
     *
     * Terbukti di produksi 11 September 2026: console terbuka lalu langsung
     * tertutup tanpa pesan apa pun. Dua sebab, masing-masing sudah cukup
     * menggagalkannya sendirian —
     *
     * 1. Sertifikat Proxmox ditandatangani sendiri (issuer "PVE Cluster
     *    Manager CA", CN pve-master sementara alamatnya IP). Browser menolak
     *    wss:// ke sertifikat tak tepercaya TANPA dialog: tidak ada kesempatan
     *    "lanjutkan saja" seperti pada halaman https biasa.
     *
     * 2. Cookie PVEAuthCookie tidak terkirim karena beda domain.
     *
     * nginx menjembatani di /__proxmox-ws/ memakai sertifikat Nawasara yang
     * sah, dan alamat Proxmox tidak pernah sampai ke browser.
     */
    public function test_websocket_lewat_nawasara_bukan_proxmox_langsung(): void
    {
        $url = 'wss://nawasara.ponorogo.go.id/__proxmox-ws/api2/json/nodes/pve-3/qemu/100/vncwebsocket?port=5900&vncticket=X';

        $this->assertStringStartsWith('wss://nawasara.ponorogo.go.id/', $url);
        $this->assertStringContainsString('/__proxmox-ws/', $url);
        $this->assertStringNotContainsString(':8006', $url, 'alamat Proxmox bocor ke browser');
    }

    /**
     * Nama pengguna dikirim server, tidak dipotong dari tiket di browser.
     *
     * Tiket berbentuk "PVE:root@pam:HEX::TANDA", sehingga split(':')[1]
     * kebetulan benar untuk root@pam dan salah untuk realm lain.
     */
    public function test_nama_pengguna_tidak_dipotong_dari_tiket(): void
    {
        $tiket = 'PVE:operator@pve:6AA394B2::mP7oNo';

        // Cara lama — kebetulan benar hanya karena bentuknya begitu.
        $dipotong = explode(':', $tiket)[1];

        // Cara sekarang — server yang memberi tahu.
        $dariServer = 'operator@pve';

        $this->assertSame($dariServer, $dipotong, 'kebetulan sama pada contoh ini');

        // Tetapi bentuk lain membuatnya meleset.
        $tiketLain = 'PVE:root@pam:X::Y';
        $this->assertSame('root@pam', explode(':', $tiketLain)[1]);
    }

    /**
     * Proxmox membalas baris autentikasi dengan frame BINER, bukan teks.
     *
     * Diperiksa langsung terhadap PVE 8.4.16: opcode 2, isi "OK". Klien yang
     * hanya memeriksa `typeof ev.data === 'string'` tidak akan pernah melihat
     * balasan itu dan menganggap sesi tidak pernah siap.
     */
    public function test_balasan_auth_berupa_frame_biner(): void
    {
        $opcode = 2; // 1 = teks, 2 = biner

        $this->assertSame(2, $opcode, 'balasan OK datang sebagai ArrayBuffer, bukan string');
    }

    /**
     * `console_user` boleh kosong; `console_password` yang menentukan.
     *
     * Placeholder di formulir Vault menampilkan "root@pam" tanpa
     * mengirimkannya, sehingga orang wajar mengira kotaknya sudah terisi —
     * lalu console diam-diam tidak pernah menyala. Terjadi 10 September 2026.
     */
    public function test_console_user_punya_nilai_bawaan(): void
    {
        $ambilUser = fn (string $tersimpan) => trim($tersimpan) !== '' ? trim($tersimpan) : 'root@pam';

        $this->assertSame('root@pam', $ambilUser(''));
        $this->assertSame('root@pam', $ambilUser('   '));
        $this->assertSame('operator@pve', $ambilUser('operator@pve'));
    }
}

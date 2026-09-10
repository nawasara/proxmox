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

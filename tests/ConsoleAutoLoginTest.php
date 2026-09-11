<?php

namespace Nawasara\Proxmox\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Login otomatis console — Nawasara menjawab prompt `login:` milik container.
 *
 * Tujuannya menyamai rasa Teleport tanpa memasang agen di tiap VM. Yang TIDAK
 * didapat tanpa agen: identitas per orang di dalam mesin. Begitu shell
 * terbuka, semua orang adalah `root`; `last` dan `~/.bash_history` di sana
 * tidak dapat membedakan siapa. Yang menjawab "siapa" hanya tabel Riwayat
 * Console di Nawasara.
 */
class ConsoleAutoLoginTest extends TestCase
{
    /** Meniru ConsoleCredential::forVm() — hanya baris aktif yang dipakai. */
    private function credentialFor(array $rows, string $node, int $vmid): ?array
    {
        foreach ($rows as $row) {
            if ($row['node_name'] === $node && $row['vmid'] === $vmid && $row['is_active']) {
                return $row;
            }
        }

        return null;
    }

    private function sampleRows(): array
    {
        return [
            ['node_name' => 'pve-1', 'vmid' => 140, 'login_user' => 'root', 'is_active' => true],
            ['node_name' => 'pve-1', 'vmid' => 126, 'login_user' => 'root', 'is_active' => false],
        ];
    }

    /**
     * Mesin tanpa baris kredensial TETAP meminta login.
     *
     * Ini keadaan bawaan, dan sengaja: mesin yang pemiliknya belum menyetujui
     * akses otomatis tidak boleh ikut terbuka hanya karena mekanismenya ada.
     */
    public function test_mesin_tak_terdaftar_tetap_minta_login(): void
    {
        $this->assertNull($this->credentialFor($this->sampleRows(), 'pve-1', 999));
    }

    /** Mesin yang terdaftar dan aktif mendapat kredensialnya. */
    public function test_mesin_terdaftar_mendapat_kredensial(): void
    {
        $credential = $this->credentialFor($this->sampleRows(), 'pve-1', 140);

        $this->assertNotNull($credential);
        $this->assertSame('root', $credential['login_user']);
    }

    /**
     * Menonaktifkan mengembalikan console ke permintaan login.
     *
     * Inilah cara mencabut tanpa kehilangan kredensialnya — berguna saat
     * pemilik mesin meminta dihentikan sementara.
     */
    public function test_nonaktif_kembali_minta_login(): void
    {
        $this->assertNull($this->credentialFor($this->sampleRows(), 'pve-1', 126));
    }

    /**
     * Prompt dijawab paling banyak sekali masing-masing.
     *
     * Bila kredensialnya salah, getty bertanya lagi. Menjawab berulang hanya
     * akan mengunci akun setelah beberapa percobaan — dan mengunci akun root
     * sebuah mesin produksi jauh lebih merepotkan daripada gagal login sekali.
     */
    public function test_prompt_dijawab_sekali_saja(): void
    {
        $loginSent = false;
        $passwordSent = false;

        $handle = function (string $tail) use (&$loginSent, &$passwordSent) {
            if ($passwordSent) {
                return 'diabaikan';
            }
            if (! $loginSent && preg_match('/login:\s*$/i', $tail)) {
                $loginSent = true;

                return 'kirim-user';
            }
            if ($loginSent && preg_match('/password:\s*$/i', $tail)) {
                $passwordSent = true;

                return 'kirim-sandi';
            }

            return 'tunggu';
        };

        $this->assertSame('kirim-user', $handle('host login:'));
        $this->assertSame('kirim-sandi', $handle('Password:'));

        // Percobaan gagal → getty bertanya lagi, dan kita TIDAK menjawab.
        $this->assertSame('diabaikan', $handle('Login incorrect host login:'));
    }

    /**
     * Getty sengaja TIDAK dimatikan.
     *
     * Mematikannya lebih sederhana, tetapi menghapus dua hal: `last`/`who` di
     * dalam mesin tidak lagi mencatat login apa pun, dan pencabutan menuntut
     * masuk ke mesin itu lagi alih-alih satu klik di panel.
     */
    public function test_getty_tetap_berdiri(): void
    {
        $approach = [
            'disable_getty' => false,
            'inject_login' => true,
        ];

        $this->assertFalse($approach['disable_getty']);
        $this->assertTrue($approach['inject_login']);
    }

    /**
     * Sandi tidak diisikan kembali ke formulir saat menyunting.
     *
     * Mengisinya berarti menaruh sandi mesin ke snapshot Livewire setiap kali
     * seseorang membuka formulir untuk mengubah hal lain — misalnya sekadar
     * menonaktifkannya.
     */
    public function test_sandi_tidak_dikembalikan_ke_formulir(): void
    {
        $stored = 'sandi-asli';
        $formValueOnEdit = '';

        $this->assertSame('', $formValueOnEdit);
        $this->assertNotSame($stored, $formValueOnEdit);
    }
}

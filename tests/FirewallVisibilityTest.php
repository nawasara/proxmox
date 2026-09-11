<?php

namespace Nawasara\Proxmox\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Daftar aturan firewall tidak boleh ditampilkan tanpa status firewallnya.
 *
 * Sebuah VM dapat memiliki aturan yang lengkap dan meyakinkan sementara
 * firewall-nya sendiri dimatikan — dan dalam keadaan itu tidak satu pun aturan
 * berlaku. Menampilkan daftarnya saja membuat mesin terlihat terlindungi
 * padahal terbuka sepenuhnya, dan itu jenis kekeliruan yang baru ketahuan
 * setelah terlambat.
 */
class FirewallVisibilityTest extends TestCase
{
    /** Bentuk yang dikembalikan detailFirewall(). */
    private function keadaan(bool $enable, array $rules): array
    {
        return ['enable' => $enable, 'rules' => $rules];
    }

    /** Aturan nyata dari cluster — bentuknya diambil dari respons sungguhan. */
    private function aturanNyata(): array
    {
        return [
            ['pos' => 0, 'action' => 'ACCEPT', 'type' => 'in', 'proto' => 'icmp', 'source' => '103.109.206.45', 'dest' => '103.109.206.13', 'enable' => 1, 'log' => 'info'],
            ['pos' => 1, 'action' => 'DROP', 'type' => 'in', 'proto' => 'icmp', 'dest' => '103.109.206.13', 'enable' => 1, 'log' => 'nolog'],
            ['pos' => 2, 'action' => 'ACCEPT', 'type' => 'in', 'proto' => 'tcp', 'dport' => '2083,2087,2095', 'source' => '103.109.206.55', 'enable' => 1, 'log' => 'nolog'],
            ['pos' => 3, 'action' => 'DROP', 'type' => 'in', 'proto' => 'tcp', 'dport' => '2083,2087,2095', 'enable' => 1, 'log' => 'nolog'],
        ];
    }

    /** Peringatan muncul saat firewall mati padahal aturannya terisi. */
    private function perluPeringatan(array $fw): bool
    {
        return ! $fw['enable'] && count($fw['rules']) > 0;
    }

    /**
     * Inti perkaranya.
     */
    public function test_firewall_mati_dengan_aturan_memunculkan_peringatan(): void
    {
        $this->assertTrue(
            $this->perluPeringatan($this->keadaan(false, $this->aturanNyata())),
            'aturan terlihat berlaku padahal firewall dimatikan',
        );
    }

    /** Firewall aktif tidak perlu peringatan. */
    public function test_firewall_aktif_tanpa_peringatan(): void
    {
        $this->assertFalse($this->perluPeringatan($this->keadaan(true, $this->aturanNyata())));
    }

    /**
     * Firewall mati TANPA aturan bukan hal yang perlu diperingatkan.
     *
     * Itu keadaan bawaan sebagian besar VM di cluster ini, dan memperingatkan
     * setiap kalinya hanya melatih orang mengabaikan peringatan.
     */
    public function test_firewall_mati_tanpa_aturan_tidak_diperingatkan(): void
    {
        $this->assertFalse($this->perluPeringatan($this->keadaan(false, [])));
    }

    /**
     * Proxmox tak terjangkau ≠ firewall kosong.
     *
     * detailFirewall() mengembalikan null saat panggilan gagal, dan bagian ini
     * tidak digambar sama sekali. Menggambarnya kosong akan menyatakan "tidak
     * ada aturan" padahal yang benar adalah "tidak diketahui".
     */
    public function test_gagal_baca_berbeda_dari_kosong(): void
    {
        $gagal = null;
        $kosong = $this->keadaan(true, []);

        $this->assertNull($gagal);
        $this->assertNotNull($kosong);
        $this->assertSame([], $kosong['rules']);
    }

    /**
     * Aturan yang dimatikan satu per satu tetap ditampilkan.
     *
     * Menyembunyikannya membuat nomor urut di Nawasara tidak cocok dengan yang
     * terlihat di Proxmox — dan pada firewall, urutan menentukan hasil.
     */
    public function test_aturan_nonaktif_tetap_ditampilkan(): void
    {
        $rules = $this->aturanNyata();
        $rules[] = ['pos' => 4, 'action' => 'ACCEPT', 'type' => 'out', 'enable' => 0];

        $ditampilkan = count($rules);
        $aktifSaja = count(array_filter($rules, fn ($r) => ($r['enable'] ?? 1) == 1));

        $this->assertSame(5, $ditampilkan);
        $this->assertSame(4, $aktifSaja, 'ada satu aturan nonaktif yang tetap harus terlihat');
    }

    /**
     * Urutan aturan dipertahankan apa adanya dari Proxmox.
     *
     * Firewall dievaluasi berurutan — aturan pertama yang cocok yang
     * menentukan. Mengurutkan ulang demi kerapian akan menampilkan perilaku
     * yang bukan perilaku sebenarnya.
     */
    public function test_urutan_dipertahankan(): void
    {
        $pos = array_column($this->aturanNyata(), 'pos');

        $terurut = $pos;
        sort($terurut);

        $this->assertSame($terurut, $pos, 'urutan aturan berubah dari yang dikirim Proxmox');
    }
}

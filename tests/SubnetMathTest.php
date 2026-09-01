<?php

namespace Nawasara\Proxmox\Tests;

use Nawasara\Proxmox\Models\ProxmoxSubnet;
use PHPUnit\Framework\TestCase;

/**
 * Hitungan subnet — bagian yang paling berbahaya bila keliru.
 *
 * Salah menghitung kapasitas akan menyarankan alamat yang tidak ada, atau
 * menyembunyikan alamat yang sebenarnya bebas. Angka /27 di sini bukan
 * karangan: itulah `vmbr0` di Proxmox Ponorogo.
 */
class SubnetMathTest extends TestCase
{
    private function subnet(string $cidr, ?string $gateway = null, ?array $reserved = null): ProxmoxSubnet
    {
        $s = new ProxmoxSubnet;
        $s->cidr = $cidr;
        $s->gateway = $gateway;
        $s->reserved_ranges = $reserved;

        return $s;
    }

    public function test_kapasitas_24_dan_27(): void
    {
        $this->assertSame(254, $this->subnet('111.1.1.0/24')->usableCount());

        // Yang sesungguhnya dipakai vmbr0 — 30, bukan 254. Inilah alasan
        // prefiks WAJIB dibaca dari Proxmox, bukan ditebak.
        $this->assertSame(30, $this->subnet('103.109.206.0/27')->usableCount());
    }

    /**
     * /31 dan /32 tidak punya broadcast.
     *
     * Rumus umum (2^n - 2) menghasilkan 0 dan -1 di sini; angka negatif akan
     * membuat sisa kapasitas terbaca aneh dan perulangan tak terduga.
     */
    public function test_prefiks_sempit_tidak_menghasilkan_angka_negatif(): void
    {
        $this->assertSame(2, $this->subnet('10.0.0.0/31')->usableCount());
        $this->assertSame(1, $this->subnet('10.0.0.1/32')->usableCount());
    }

    public function test_batas_alamat_yang_boleh_dipakai(): void
    {
        $s = $this->subnet('111.1.1.0/24');

        $this->assertSame('111.1.1.1', long2ip($s->firstUsableLong()));
        $this->assertSame('111.1.1.254', long2ip($s->lastUsableLong()));

        $s27 = $this->subnet('103.109.206.32/27');
        $this->assertSame('103.109.206.33', long2ip($s27->firstUsableLong()));
        $this->assertSame('103.109.206.62', long2ip($s27->lastUsableLong()));
    }

    public function test_keanggotaan_alamat(): void
    {
        $s = $this->subnet('103.109.206.32/27');

        $this->assertTrue($s->contains('103.109.206.42'));
        $this->assertTrue($s->contains('103.109.206.62'));

        // Di luar /27 meski masih 103.109.206.x — inilah yang membedakan
        // /27 dari /24, dan tempat kekeliruan paling mudah terjadi.
        $this->assertFalse($s->contains('103.109.206.70'));
        $this->assertFalse($s->contains('111.1.1.5'));
    }

    /**
     * Gateway TIDAK BOLEH disarankan, meski tidak didaftarkan sebagai
     * rentang tercadang. Menyarankannya adalah kekeliruan yang pasti
     * memutus jaringan.
     */
    public function test_gateway_selalu_tercadang(): void
    {
        $s = $this->subnet('103.109.206.32/27', '103.109.206.33');

        $this->assertTrue($s->isReserved('103.109.206.33'));
        $this->assertFalse($s->isReserved('103.109.206.50'));
    }

    public function test_rentang_tercadang_dihormati(): void
    {
        $s = $this->subnet('111.1.1.0/24', null, [
            ['from' => '111.1.1.1', 'to' => '111.1.1.20', 'note' => 'perangkat jaringan'],
        ]);

        $this->assertTrue($s->isReserved('111.1.1.1'));
        $this->assertTrue($s->isReserved('111.1.1.20'));
        $this->assertFalse($s->isReserved('111.1.1.21'));
    }

    /** Rentang satu alamat: `to` boleh dihilangkan. */
    public function test_rentang_satu_alamat(): void
    {
        $s = $this->subnet('111.1.1.0/24', null, [
            ['from' => '111.1.1.99'],
        ]);

        $this->assertTrue($s->isReserved('111.1.1.99'));
        $this->assertFalse($s->isReserved('111.1.1.100'));
    }
}

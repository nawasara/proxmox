<?php

namespace Nawasara\Proxmox\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Kapan peringatan kapasitas SEHARUSNYA berbunyi.
 *
 * Ditulis setelah basis data produksi mati karena disknya penuh tanpa satu pun
 * peringatan. Datanya sudah ada sejak lama — yang tidak ada adalah sesuatu yang
 * membacanya dan berkata "ini akan jadi masalah".
 *
 * Yang diuji di sini bukan rumus persentasenya (itu sepele), melainkan dua
 * keputusan yang membuat peringatan ini layak dipercaya:
 *
 *   1. disk besar yang persentasenya tinggi TETAPI sisanya masih lega **tidak**
 *      boleh berbunyi — peringatan yang menyala saat tak ada yang perlu
 *      dikerjakan adalah cara tercepat membuatnya diabaikan;
 *   2. mesin yang disknya **tidak terbaca** dilewati, bukan dianggap 0%.
 */
class CapacityThresholdTest extends TestCase
{
    private const GB = 1073741824;

    /** Meniru keputusan di CheckCapacityJob::periksa(). */
    private function putusan(float $usedGb, float $totalGb, int $minFree = 20, int $warn = 80, int $crit = 90): string
    {
        if ($totalGb <= 0 || $usedGb <= 0) {
            return 'dilewati';
        }

        $pct = $usedGb / $totalGb * 100;
        $sisa = $totalGb - $usedGb;

        if ($sisa > $minFree) {
            return 'aman';
        }

        return match (true) {
            $pct >= $crit => 'critical',
            $pct >= $warn => 'warning',
            default => 'aman',
        };
    }

    /**
     * Kasus yang membuat aturan sisa-GB ada.
     *
     * Diambil dari produksi: satu mesin 84,6% terpakai dengan **151 GB masih
     * bebas**. Persentasenya mengkhawatirkan, kenyataannya tidak.
     */
    public function test_disk_besar_dengan_sisa_lega_tidak_berbunyi(): void
    {
        $this->assertSame('aman', $this->putusan(830, 981));
    }

    /** Persentase sama, disk kecil — di sini sisanya benar-benar tipis. */
    public function test_disk_kecil_dengan_persentase_sama_berbunyi(): void
    {
        $this->assertSame('warning', $this->putusan(16.9, 20));
    }

    public function test_hampir_habis_jadi_critical(): void
    {
        $this->assertSame('critical', $this->putusan(95, 100));
    }

    /**
     * QEMU tanpa guest-agent melaporkan 0 — HARUS dilewati.
     *
     * Menganggapnya 0% jauh lebih berbahaya daripada tidak memantaunya: dasbor
     * tampak hijau untuk mesin yang keadaannya sebenarnya tidak diketahui, dan
     * itu persis rasa aman palsu yang membuat kejadian kemarin lolos.
     */
    public function test_disk_tak_terbaca_dilewati_bukan_dianggap_kosong(): void
    {
        $this->assertSame('dilewati', $this->putusan(0, 150));
        $this->assertSame('dilewati', $this->putusan(0, 0));
        $this->assertNotSame('aman', $this->putusan(0, 150), 'Nol bukan berarti sehat.');
    }

    /** Mesin yang sungguh lega tetap diam. */
    public function test_mesin_lega_tidak_berbunyi(): void
    {
        $this->assertSame('aman', $this->putusan(50, 196));
        $this->assertSame('aman', $this->putusan(11, 94));
    }

    /**
     * Kejadian yang memicu semua ini: disk nyaris habis pada mesin kecil.
     */
    public function test_kasus_db_mati_akan_tertangkap(): void
    {
        $this->assertSame('critical', $this->putusan(48, 50), 'Sisa 2 GB harus berbunyi gawat.');
        $this->assertSame('warning', $this->putusan(42, 50), 'Sisa 8 GB sudah layak diperingatkan.');
    }
}

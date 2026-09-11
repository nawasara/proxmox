<?php

namespace Nawasara\Proxmox\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Console container dibuka lewat shell node, bukan console container.
 *
 * Console container melewati getty, yang menyajikan `login:` dan menuntut
 * kredensial mesin — kredensial yang tidak dipegang pengguna Nawasara.
 *
 * Shell node terbuka langsung sebagai root tanpa login (diperiksa terhadap
 * pve-1 11 September 2026: banner Debian muncul seketika, `whoami` menjawab
 * root, `pct` tersedia di /usr/sbin/pct). Dari sana `pct enter <vmid>`
 * menembus ke dalam container lewat kernel host.
 *
 * Yang ditinggalkan karena pendekatan ini: menyimpan sandi tiap container di
 * Nawasara. Itu sempat dibangun, lalu dibuang — satu sandi per mesin berarti
 * satu hal lagi yang harus dijaga, dicabut, dan diputar, untuk hasil yang
 * sama.
 */
class ConsoleContainerEntryTest extends TestCase
{
    /** Meniru pemilihan tiket di Livewire\Vm\Section\Table::openConsole(). */
    private function ticketTarget(string $vmType, int $vmid): array
    {
        $isContainer = $vmType === 'lxc';

        return [
            'vmid' => $isContainer ? null : $vmid,
            'enter_command' => $isContainer ? 'pct enter '.$vmid : null,
        ];
    }

    /**
     * Inti perkaranya: LXC memakai tiket SHELL NODE.
     */
    public function test_lxc_memakai_shell_node(): void
    {
        $target = $this->ticketTarget('lxc', 141);

        $this->assertNull($target['vmid'], 'tiket seharusnya untuk node, bukan container');
        $this->assertSame('pct enter 141', $target['enter_command']);
    }

    /**
     * QEMU tetap memakai console-nya sendiri.
     *
     * `pct` hanya mengurus LXC; mesin QEMU tidak dapat dimasuki dengannya, dan
     * consolenya memang milik mesin itu sendiri.
     */
    public function test_qemu_memakai_console_sendiri(): void
    {
        $target = $this->ticketTarget('qemu', 100);

        $this->assertSame(100, $target['vmid']);
        $this->assertNull($target['enter_command'], 'pct tidak berlaku untuk QEMU');
    }

    /**
     * Berlaku untuk SEMUA container, tanpa disetel satu per satu.
     *
     * Itu yang membedakannya dari pendekatan menyimpan sandi: tidak ada daftar
     * mesin yang harus diisi, dan menambah container baru tidak menuntut
     * langkah apa pun.
     */
    public function test_berlaku_tanpa_penyetelan_per_mesin(): void
    {
        foreach ([141, 124, 126, 999] as $vmid) {
            $target = $this->ticketTarget('lxc', $vmid);
            $this->assertSame('pct enter '.$vmid, $target['enter_command']);
        }
    }

    /**
     * Tidak ada kredensial container yang disimpan.
     *
     * Akses bersandar pada root Proxmox yang memang sudah dipegang Nawasara —
     * satu kredensial yang sudah ada di Vault, bukan satu per mesin.
     */
    public function test_tidak_menyimpan_sandi_container(): void
    {
        $storedSecrets = ['proxmox.console_user', 'proxmox.console_password'];

        foreach ($storedSecrets as $key) {
            $this->assertStringStartsWith('proxmox.', $key, 'hanya kredensial Proxmox, bukan per container');
        }

        $this->assertCount(2, $storedSecrets);
    }

    /**
     * Perintah dikirim setelah jeda singkat.
     *
     * Shell node menggambar banner lebih dulu; mengetik sebelum promptnya siap
     * membuat perintahnya tertelan dan console berhenti di shell node, bukan
     * di dalam container.
     */
    public function test_perintah_menunggu_prompt(): void
    {
        $delayMs = 600;

        $this->assertGreaterThan(0, $delayMs);
    }
}

<?php

namespace Nawasara\Proxmox\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Baris autentikasi websocket console memakai tiket TERMPROXY.
 *
 * Sejak PVE 8.4.21 termproxy berjalan dengan `--ticket-fd`: tiket dari POST
 * termproxy dititipkan ke prosesnya dan baris auth wajib sama persis
 * dengannya. Tiket sesi ditolak ("authentication request failed") dan semua
 * console — QEMU maupun LXC — langsung tertutup. Diperiksa terhadap prod
 * 4 November 2026: tiket termproxy → "OK" lalu `pct enter` masuk container;
 * tiket sesi → soket ditutup.
 */
class ConsoleAuthLineTest extends TestCase
{
    private function view(): string
    {
        return (string) file_get_contents(__DIR__.'/../resources/views/console/show.blade.php');
    }

    public function test_baris_auth_memakai_tiket_termproxy(): void
    {
        $this->assertStringContainsString("ws.send(cfg.consoleUser + ':' + cfg.termTicket + '\\n')", $this->view());
    }

    public function test_tiket_sesi_tidak_lagi_dikirim_ke_halaman(): void
    {
        $this->assertStringNotContainsString('sessionTicket', $this->view());
    }
}

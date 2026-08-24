<?php

namespace Nawasara\Proxmox\Tests;

use Nawasara\Proxmox\Services\NetworkConfigParser;
use PHPUnit\Framework\TestCase;

/**
 * Contoh di sini disalin APA ADANYA dari Proxmox produksi Ponorogo
 * (24 Agustus 2026), bukan dikarang — bentuk string inilah yang menentukan
 * benar-tidaknya seluruh inventori.
 */
class NetworkConfigParserTest extends TestCase
{
    private NetworkConfigParser $parser;

    protected function setUp(): void
    {
        $this->parser = new NetworkConfigParser();
    }

    public function test_lxc_memberi_ip_publik_dan_lokal(): void
    {
        $hasil = $this->parser->parse([
            'net0' => 'name=eth0,bridge=vmbr1,firewall=1,hwaddr=BC:24:11:B6:9C:3E,ip=111.1.1.53/24,type=veth',
            'net1' => 'name=eth1,bridge=vmbr1,firewall=1,gw=10.1.1.10,hwaddr=BC:24:11:42:26:71,ip=10.1.1.22/24,tag=10,type=veth',
        ]);

        $this->assertCount(2, $hasil);

        $this->assertSame('net0', $hasil[0]['nic']);
        $this->assertSame('BC:24:11:B6:9C:3E', $hasil[0]['mac']);
        $this->assertSame('vmbr1', $hasil[0]['bridge']);
        $this->assertSame('111.1.1.53', $hasil[0]['ips'][0]['ip']);
        $this->assertSame(24, $hasil[0]['ips'][0]['prefix']);

        $this->assertSame('10.1.1.22', $hasil[1]['ips'][0]['ip']);
        $this->assertSame('10.1.1.10', $hasil[1]['gateway']);
    }

    /**
     * Inti persoalannya: QEMU memberi MAC tetapi TIDAK memberi IP.
     *
     * Bila uji ini kelak "diperbaiki" agar mengembalikan IP, berarti ada yang
     * menebak — dan tebakan itulah yang menyebabkan bentrok alamat.
     */
    public function test_qemu_memberi_mac_tanpa_ip(): void
    {
        $hasil = $this->parser->parse([
            'net0' => 'virtio=46:96:DB:E3:84:08,bridge=vmbr0,firewall=1',
            'net1' => 'virtio=BC:24:11:44:A5:A9,bridge=vmbr1,firewall=1',
        ]);

        $this->assertCount(2, $hasil);
        $this->assertSame('46:96:DB:E3:84:08', $hasil[0]['mac']);
        $this->assertSame('vmbr0', $hasil[0]['bridge']);
        $this->assertSame([], $hasil[0]['ips'], 'QEMU tidak menyimpan IP di config');
    }

    /** MAC dicari sebagai pola, supaya model kartu baru tidak memutusnya. */
    public function test_mac_terbaca_apa_pun_model_kartunya(): void
    {
        foreach (['virtio', 'e1000', 'vmxnet3', 'rtl8139'] as $model) {
            $hasil = $this->parser->parse([
                'net0' => "{$model}=AA:BB:CC:DD:EE:FF,bridge=vmbr0",
            ]);

            $this->assertSame('AA:BB:CC:DD:EE:FF', $hasil[0]['mac'], "model {$model}");
        }
    }

    /** QEMU ber-cloud-init menaruh alamatnya di `ipconfigN` yang terpisah. */
    public function test_ipconfig_cloudinit_terbaca(): void
    {
        $hasil = $this->parser->parse([
            'net0' => 'virtio=AA:BB:CC:DD:EE:FF,bridge=vmbr0',
            'ipconfig0' => 'ip=103.109.206.50/27,gw=103.109.206.33',
        ]);

        $this->assertSame('103.109.206.50', $hasil[0]['ips'][0]['ip']);
        $this->assertSame(27, $hasil[0]['ips'][0]['prefix']);
    }

    /**
     * `ip=dhcp` TIDAK boleh menghasilkan alamat.
     *
     * Alamatnya belum ditentukan; mengarangnya lebih buruk daripada mengakui
     * tidak tahu, karena inventori yang salah menuntun orang ke bentrok.
     */
    public function test_dhcp_tidak_menghasilkan_alamat(): void
    {
        $hasil = $this->parser->parse([
            'net0' => 'name=eth0,bridge=vmbr1,hwaddr=BC:24:11:00:00:01,ip=dhcp,type=veth',
        ]);

        $this->assertSame([], $hasil[0]['ips']);
        $this->assertSame('BC:24:11:00:00:01', $hasil[0]['mac']);
    }

    /** Urutannya net0, net1, net2 — bukan urutan kunci dari PHP. */
    public function test_nic_terurut_wajar(): void
    {
        $hasil = $this->parser->parse([
            'net2' => 'virtio=AA:BB:CC:DD:EE:03,bridge=vmbr0',
            'net0' => 'virtio=AA:BB:CC:DD:EE:01,bridge=vmbr0',
            'net10' => 'virtio=AA:BB:CC:DD:EE:10,bridge=vmbr0',
            'net1' => 'virtio=AA:BB:CC:DD:EE:02,bridge=vmbr0',
        ]);

        $this->assertSame(
            ['net0', 'net1', 'net2', 'net10'],
            array_column($hasil, 'nic'),
        );
    }

    /** Kunci selain netN diabaikan — config VM memuat puluhan kunci lain. */
    public function test_kunci_lain_diabaikan(): void
    {
        $hasil = $this->parser->parse([
            'cores' => 4,
            'memory' => 8192,
            'scsi0' => 'local-lvm:vm-100-disk-0,size=32G',
            'net0' => 'virtio=AA:BB:CC:DD:EE:FF,bridge=vmbr0',
        ]);

        $this->assertCount(1, $hasil);
    }
}

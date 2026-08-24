<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NIC yang ADA tetapi IP-nya belum diketahui.
 *
 * Separuh NIC di Ponorogo berada di sini (31 dari 67): VM QEMU tidak menyimpan
 * IP di config, guest agent tidak terpasang, dan `/execute` — satu-satunya
 * jalan membaca tabel ARP node lewat API — menolak token API karena menuntut
 * root@pam.
 *
 * ⚠️ Tabel ini adalah alasan daftar "IP bebas" tidak boleh dipercaya bulat
 * bulat. Selama masih ada barisnya, sebuah alamat yang tampak bebas dapat
 * saja sedang dipakai salah satu NIC ini. Itu sebabnya jumlahnya ditampilkan
 * MENYATU dengan daftar IP bebas, bukan disembunyikan di halaman lain.
 *
 * Barisnya hilang sendiri begitu IP-nya diisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_proxmox_unknown_nics', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vm_id')->nullable()
                ->constrained('nawasara_proxmox_vms')->cascadeOnDelete();

            $table->unsignedInteger('vmid');
            $table->string('vm_name', 200)->nullable();
            $table->string('node_name', 100)->nullable();
            $table->string('vm_type', 10)->default('qemu');
            $table->string('vm_status', 30)->nullable();

            $table->string('nic', 20);                     // net0, net1
            $table->string('mac', 17)->nullable();         // penuntun utama
            $table->string('bridge', 50)->nullable();      // menyempitkan ke subnet mana

            // Bridge memberi tahu subnet yang MUNGKIN — pengisi tinggal
            // memilih alamat di dalamnya, bukan menebak dari nol.
            $table->foreignId('suggested_subnet_id')->nullable()
                ->constrained('nawasara_proxmox_subnets')->nullOnDelete();

            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['vmid', 'nic']);
            $table->index('node_name');
            $table->index('mac');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_proxmox_unknown_nics');
    }
};

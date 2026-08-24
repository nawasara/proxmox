<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolam IP yang dikenal, satu baris per bridge-subnet.
 *
 * Diisi dari `/nodes/{node}/network` — bukan diketik admin — supaya netmask
 * yang dipakai adalah yang SUNGGUHAN. Perbedaannya menentukan: `vmbr0` di
 * Ponorogo adalah prefiks /27 (30 alamat), bukan /24 (254). Menebaknya /24 akan
 * menyarankan alamat yang tidak pernah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_proxmox_subnets', function (Blueprint $table) {
            $table->id();

            // 111.1.1.0/24 — bentuk ternormalkan, dipakai mencocokkan alamat.
            $table->string('cidr', 43)->unique();

            $table->string('bridge', 50)->nullable();      // vmbr0, vmbr1
            $table->string('gateway', 45)->nullable();
            $table->string('label', 100)->nullable();      // "Publik", "Lokal"

            // Publik atau privat — dihitung dari alamatnya, disimpan supaya
            // dapat difilter dan diurutkan tanpa menghitung ulang.
            $table->boolean('is_public')->default(false);

            /**
             * Rentang yang TIDAK BOLEH disarankan.
             *
             * Gateway, alamat node, perangkat jaringan — hal yang hidup di
             * subnet tetapi tidak akan pernah muncul sebagai VM Proxmox.
             * Tanpa ini, daftar "bebas" akan menyarankan gateway.
             *
             * Bentuk: [["from" => "111.1.1.1", "to" => "111.1.1.20", "note" => "..."]]
             */
            $table->json('reserved_ranges')->nullable();

            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_proxmox_subnets');
    }
};

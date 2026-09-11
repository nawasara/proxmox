<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kredensial login DI DALAM container, per mesin.
     *
     * Berbeda dari `console_user`/`console_password` di Vault, yang dipakai
     * untuk berbicara dengan API Proxmox. Yang ini dipakai menjawab prompt
     * `login:` milik container itu sendiri.
     *
     * ⚠️ Kenapa per container, bukan satu untuk semua.
     *
     * Sebagian mesin di cluster ini bukan milik tim Kominfo — Bale-Production
     * dan sadap, misalnya. Satu kredensial bersama berarti mencabut akses ke
     * satu mesin mustahil tanpa mencabut semuanya, dan pemilik mesin tidak
     * pernah punya cara menarik persetujuannya kembali.
     *
     * Baris yang tidak ada berarti console mesin itu tetap meminta login
     * seperti biasa — itu keadaan bawaan, dan menghapus baris adalah cara
     * mencabutnya.
     */
    public function up(): void
    {
        Schema::create('nawasara_proxmox_console_credentials', function (Blueprint $table) {
            $table->id();

            // Identitas mesin, bukan foreign key ke nawasara_proxmox_vms:
            // tabel itu diisi ulang tiap sinkronisasi, dan kredensial tidak
            // boleh ikut hilang ketika sebuah VM sesaat tidak terbaca.
            $table->string('node_name');
            $table->unsignedInteger('vmid');

            // Disalin agar daftar tetap terbaca meski VM-nya sudah lenyap.
            $table->string('vm_name')->nullable();

            $table->string('login_user')->default('root');

            // Terenkripsi lewat cast 'encrypted' di model — kunci yang sama
            // dengan Vault, sehingga APP_KEY tetap satu-satunya yang membuka.
            $table->text('login_password');

            // Mematikan tanpa menghapus: berguna saat pemilik mesin meminta
            // dihentikan sementara, dan kredensialnya masih akan dipakai lagi.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['node_name', 'vmid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_proxmox_console_credentials');
    }
};

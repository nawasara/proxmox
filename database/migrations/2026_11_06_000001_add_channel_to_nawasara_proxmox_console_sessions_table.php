<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asal sesi console: `panel` (admin Nawasara, akun root@pam) atau `kisara`
 * (PIC container lewat Akun Kisara, akun kisara-console@pve). Keduanya
 * berbeda hak dan berbeda pertanggungjawaban, jadi riwayat akses harus dapat
 * memisahkannya. Baris lama semuanya dari panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nawasara_proxmox_console_sessions', function (Blueprint $table) {
            $table->string('channel', 20)->default('panel')->after('user_nip');
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_proxmox_console_sessions', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};

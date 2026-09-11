<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat pembukaan console VM.
     *
     * Console memberi shell root pada mesin produksi, dan kredensial yang
     * dipakainya SATU untuk semua orang (`root@pam` dari Vault). Di sisi
     * Proxmox setiap sesi karena itu tampak sebagai root — tidak ada jejak
     * siapa yang membukanya.
     *
     * Tabel inilah satu-satunya tempat pertanyaan "siapa masuk ke mesin ini,
     * kapan, dan berapa lama" dapat dijawab.
     *
     * ⚠️ Yang TIDAK dicatat: apa yang diketik di dalam shell. Merekamnya
     * berarti ikut merekam kata sandi yang diketik orang di sana, dan tempat
     * penyimpanan rekaman itu akan menjadi sasaran yang lebih berharga
     * daripada mesin yang dilindunginya.
     */
    public function up(): void
    {
        Schema::create('nawasara_proxmox_console_sessions', function (Blueprint $table) {
            // bigint: baris ini catatan internal, tidak pernah dirujuk dari
            // luar sistem. Lihat panduan §10a.
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // ⚠️ Nama dan surel DISALIN, bukan hanya direlasikan.
            //
            // Akun dapat berganti nama, berpindah orang, atau dihapus — dan
            // catatan yang hanya menyimpan user_id akan ikut berubah artinya
            // atau lenyap bersamanya. Sebuah jejak audit harus tetap terbaca
            // apa adanya bertahun-tahun kemudian.
            $table->string('user_name');
            $table->string('user_email')->nullable();

            // Disiapkan sekarang, diisi begitu sumber NIP diputuskan — tidak
            // ada di tabel users maupun atribut Keycloak saat ini.
            $table->string('user_nip', 32)->nullable();

            $table->string('node_name');
            $table->unsignedInteger('vmid');
            $table->string('vm_name');
            $table->string('vm_type', 8)->default('qemu');

            $table->timestamp('started_at');

            // Kosong berarti sesi belum ditutup dengan semestinya — tab
            // ditutup paksa, jaringan putus, atau peramban berhenti. Itu
            // keadaan yang wajar, bukan galat, dan sengaja dibiarkan terbaca
            // apa adanya alih-alih ditebak.
            $table->timestamp('ended_at')->nullable();

            // Detik. Dihitung saat sesi ditutup; null selama masih berjalan.
            $table->unsignedInteger('duration_seconds')->nullable();

            // Denyut terakhir dari halaman console. Dipakai menutup sesi yang
            // terlanjur menggantung, dan membedakan "masih dibuka" dari
            // "ditinggalkan sejak lama".
            $table->timestamp('last_seen_at')->nullable();

            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index(['vmid', 'started_at']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_proxmox_console_sessions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris per alamat IP yang diketahui terpakai.
 *
 * Menggabungkan dua asal yang TIDAK setara, dan perbedaannya sengaja
 * disimpan (kolom `source`):
 *
 *   config  — dibaca dari config VM di Proxmox. Tepercaya, diperbarui tiap
 *             sinkronisasi. Hanya ada pada LXC; QEMU tidak menyimpan IP di
 *             config-nya.
 *   manual  — diketik orang. Satu-satunya cara mengetahui IP VM QEMU di sini,
 *             karena guest agent tidak terpasang dan endpoint `/execute`
 *             Proxmox menolak token API (menuntut root@pam).
 *
 * ⚠️ Baris `manual` TIDAK boleh ditimpa sinkronisasi. Ia mewakili
 * pengetahuan yang tidak dimiliki Proxmox; menghapusnya berarti membuang
 * satu-satunya catatan yang ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_proxmox_ip_addresses', function (Blueprint $table) {
            $table->id();

            $table->string('ip', 45);

            /**
             * Bentuk yang dapat DIURUTKAN sebagai angka.
             *
             * Diurutkan sebagai teks, "111.1.1.9" jatuh setelah "111.1.1.10"
             * dan daftarnya membingungkan. Disimpan sekali saat menulis
             * daripada dihitung di setiap kueri.
             */
            $table->unsignedBigInteger('ip_numeric')->nullable();

            $table->foreignId('subnet_id')->nullable()
                ->constrained('nawasara_proxmox_subnets')->nullOnDelete();

            // VM pemakainya. Null berarti terpakai tetapi belum diketahui
            // milik siapa — masih lebih berguna daripada tidak dicatat.
            $table->foreignId('vm_id')->nullable()
                ->constrained('nawasara_proxmox_vms')->cascadeOnDelete();

            $table->unsignedInteger('vmid')->nullable();   // tetap ada meski VM terhapus
            $table->string('vm_name', 200)->nullable();
            $table->string('node_name', 100)->nullable();

            $table->string('nic', 20)->nullable();         // net0, net1
            $table->string('mac', 17)->nullable();         // penuntun mengisi manual
            $table->string('bridge', 50)->nullable();

            $table->enum('source', ['config', 'manual'])->default('config');

            // Siapa mengisi, kapan — hanya untuk baris manual.
            $table->string('filled_by', 100)->nullable();
            $table->text('note')->nullable();

            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            // Satu IP boleh muncul sekali per NIC per VM; IP yang sama di dua
            // VM adalah BENTROK, dan itu justru yang ingin terlihat — maka
            // `ip` sendiri sengaja TIDAK unik.
            $table->unique(['ip', 'vmid', 'nic']);

            $table->index('ip');
            $table->index('ip_numeric');
            $table->index('source');
            $table->index('mac');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_proxmox_ip_addresses');
    }
};

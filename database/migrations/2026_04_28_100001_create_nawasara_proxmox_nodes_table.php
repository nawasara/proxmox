<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_proxmox_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('node_name', 100)->unique();
            $table->string('status', 30)->default('unknown'); // online, offline, unknown
            $table->string('type', 30)->default('node'); // node | cluster

            // Resource snapshots — last sync values, RRD live separately
            $table->bigInteger('cpu_count')->nullable();
            $table->float('cpu_usage', 6, 4)->nullable(); // 0..1
            $table->bigInteger('mem_total')->nullable();
            $table->bigInteger('mem_used')->nullable();
            $table->bigInteger('disk_total')->nullable();
            $table->bigInteger('disk_used')->nullable();
            $table->bigInteger('uptime')->nullable(); // seconds

            $table->string('pve_version', 50)->nullable();
            $table->string('kernel_version', 100)->nullable();

            // HasSyncStatus
            $table->string('sync_status', 30)->default('synced');
            $table->text('sync_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('content_hash', 64)->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('sync_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_proxmox_nodes');
    }
};

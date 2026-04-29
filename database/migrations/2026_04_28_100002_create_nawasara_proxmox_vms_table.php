<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_proxmox_vms', function (Blueprint $table) {
            $table->id();

            // Proxmox identity
            $table->unsignedInteger('vmid'); // numeric VM id from Proxmox
            $table->string('node_name', 100); // which node hosts this VM
            $table->enum('vm_type', ['qemu', 'lxc']); // qemu = full VM, lxc = container
            $table->string('name', 200)->nullable(); // VM display name

            // Lifecycle status
            $table->string('status', 30)->default('unknown'); // running, stopped, paused, suspended
            $table->string('lock', 30)->nullable(); // backup, snapshot, migrate, rollback, etc.
            $table->boolean('template')->default(false);

            // Resources (snapshot — RRD live tidak di-cache)
            $table->bigInteger('cpu_count')->nullable();
            $table->float('cpu_usage', 6, 4)->nullable(); // 0..1
            $table->bigInteger('mem_total')->nullable();
            $table->bigInteger('mem_used')->nullable();
            $table->bigInteger('disk_total')->nullable();
            $table->bigInteger('disk_used')->nullable();
            $table->bigInteger('uptime')->nullable();

            // Network — primary IP yang ke-detect (kalau ada qemu-guest-agent)
            $table->json('ip_addresses')->nullable();

            // Tags + description metadata
            $table->json('tags')->nullable();
            $table->text('description')->nullable();

            // HasSyncStatus
            $table->string('sync_status', 30)->default('synced');
            $table->text('sync_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('content_hash', 64)->nullable();

            $table->timestamps();

            $table->unique(['vmid', 'vm_type']);
            $table->index('node_name');
            $table->index('status');
            $table->index('vm_type');
            $table->index('sync_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_proxmox_vms');
    }
};

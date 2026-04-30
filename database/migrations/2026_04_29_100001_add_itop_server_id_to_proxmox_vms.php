<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Future cross-link placeholder for iTop CMDB.
 *
 * Once nawasara/itop ships, the sync job will populate this with the
 * iTop ItopServer.id (finalclass=VirtualMachine) matched by MAC or IP.
 * Nullable, no FK — iTop runs in a separate database, so we treat the
 * id as an opaque string.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nawasara_proxmox_vms', function (Blueprint $table) {
            $table->string('itop_server_id', 64)->nullable()->after('description')->index();
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_proxmox_vms', function (Blueprint $table) {
            $table->dropIndex(['itop_server_id']);
            $table->dropColumn('itop_server_id');
        });
    }
};

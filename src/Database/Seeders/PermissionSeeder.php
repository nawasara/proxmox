<?php

namespace Nawasara\Proxmox\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'proxmox.node.view',
            'proxmox.vm.view',
            'proxmox.vm.lifecycle',   // start / stop / restart / shutdown — Phase 2
            'proxmox.vm.snapshot',    // create / rollback / delete snapshot — Phase 3
            'proxmox.vm.console',
            'proxmox.console.history',   // riwayat siapa membuka console VM
            'proxmox.sync.execute',

            // Inventori IP. `edit` dipisah dari `view` karena mengisi alamat
            // secara manual adalah menyatakan sesuatu yang tidak diketahui
            // Proxmox — isian yang keliru menuntun orang ke bentrok alamat.
            'proxmox.ip.view',
            'proxmox.ip.edit',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $role = Role::where('name', 'developer')->first();
        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }
}

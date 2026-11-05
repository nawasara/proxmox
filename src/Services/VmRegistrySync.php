<?php

namespace Nawasara\Proxmox\Services;

use Nawasara\Proxmox\Models\ProxmoxVm;

/**
 * Mirrors every Proxmox VM/LXC into nawasara/registry as a "server" asset, so
 * a VM gets PICs (primary + backups) through the same registry machinery as
 * domains and cPanel accounts — and the ASN account portal (Akun Kisara) can
 * show each civil servant the servers they look after.
 *
 * Same pattern as nawasara/whm's AccountRegistrySync:
 *  - keyed by (package_ref = proxmox, external_id = vmid). vmid, not the
 *    proxmox row id: SyncProxmoxVmsJob hard-deletes rows for VMs that vanish
 *    and recreates them, and the PIC must survive that.
 *  - a VM that disappears from the cluster is marked inactive, never deleted,
 *    so its PIC history and audit trail stay.
 *  - templates are skipped: nobody is responsible for a template.
 *
 * Registry is a soft dependency — without it this does nothing.
 */
class VmRegistrySync
{
    public const PACKAGE_REF = 'proxmox';

    public const ASSET_TYPE = 'server';

    public static function available(): bool
    {
        return class_exists(\Nawasara\Registry\Models\Asset::class);
    }

    /** @return array{created:int, updated:int, unchanged:int, deactivated:int} */
    public function sync(): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'deactivated' => 0];

        if (! self::available()) {
            return $stats;
        }

        $asset = \Nawasara\Registry\Models\Asset::class;
        $existing = $asset::where('package_ref', self::PACKAGE_REF)->get()->keyBy('external_id');
        $seen = [];

        foreach (ProxmoxVm::where('template', false)->get() as $vm) {
            $key = (string) $vm->vmid;
            $seen[] = $key;
            $identifier = self::identifierFor($vm);
            $notes = 'Proxmox '.($vm->vm_type === 'lxc' ? 'LXC' : 'VM').' #'.$vm->vmid.' di node '.$vm->node_name;

            $row = $existing->get($key);

            if (! $row) {
                $asset::create([
                    'opd_id' => null,
                    'pj_user_id' => null,
                    'type' => self::ASSET_TYPE,
                    'identifier' => $identifier,
                    'package_ref' => self::PACKAGE_REF,
                    'external_id' => $key,
                    'status' => 'active',
                    'registered_at' => now(),
                    'discovered_at' => now(),
                    'notes' => $notes,
                ]);
                $stats['created']++;

                continue;
            }

            // Status follows existence, not power: a stopped VM still has an
            // owner. Only a VM that left the cluster is inactive.
            $changes = array_filter([
                'identifier' => $row->identifier !== $identifier ? $identifier : null,
                'status' => $row->status !== 'active' ? 'active' : null,
            ]);

            if ($changes) {
                $row->update($changes);
                $stats['updated']++;
            } else {
                $stats['unchanged']++;
            }
        }

        // Guard: an empty VM list means the sync read nothing (API down), not
        // that every VM was deleted. Deactivating on that would empty Akun
        // Kisara for every PIC at once.
        if ($seen) {
            $stats['deactivated'] = $asset::where('package_ref', self::PACKAGE_REF)
                ->where('status', 'active')
                ->whereNotIn('external_id', $seen)
                ->update(['status' => 'inactive']);
        }

        return $stats;
    }

    /**
     * "name (#vmid)": VM names repeat across a cluster (several "ubuntu"),
     * and the vmid is what an admin types into Proxmox to find it.
     */
    public static function identifierFor(ProxmoxVm $vm): string
    {
        return ($vm->name ?: 'vm').' (#'.$vm->vmid.')';
    }
}

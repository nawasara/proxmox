<?php

namespace Nawasara\Proxmox\Console\Commands;

use Illuminate\Console\Command;
use Nawasara\Proxmox\Jobs\SyncProxmoxNodesJob;
use Nawasara\Proxmox\Jobs\SyncProxmoxVmsJob;

/**
 * Sync Proxmox cluster (nodes + VMs/containers) ke DB snapshot.
 *
 * Default: dispatch job ke queue (async). Scheduler memakai mode ini.
 * Flag --sync menjalankan job synchronously — untuk first run / debug.
 */
class SyncCommand extends Command
{
    protected $signature = 'proxmox:sync
                            {--nodes : Hanya sync nodes}
                            {--vms : Hanya sync VMs/containers}
                            {--sync : Jalankan synchronous (skip queue) — untuk debug}';

    protected $description = 'Sync Proxmox nodes + VMs ke DB snapshot. Default: dispatch job ke queue.';

    public function handle(): int
    {
        $onlyNodes = (bool) $this->option('nodes');
        $onlyVms = (bool) $this->option('vms');
        $runSync = (bool) $this->option('sync');

        // Tanpa flag spesifik → sync keduanya.
        $doNodes = $onlyNodes || (! $onlyNodes && ! $onlyVms);
        $doVms = $onlyVms || (! $onlyNodes && ! $onlyVms);

        if ($doNodes) {
            $this->dispatchJob(SyncProxmoxNodesJob::class, 'nodes', $runSync);
        }

        if ($doVms) {
            $this->dispatchJob(SyncProxmoxVmsJob::class, 'VMs', $runSync);
        }

        return self::SUCCESS;
    }

    /**
     * @param  class-string  $jobClass
     */
    protected function dispatchJob(string $jobClass, string $label, bool $runSync): void
    {
        $job = new $jobClass(triggerSource: 'scheduled');

        if ($runSync) {
            try {
                $job->handle();
                $this->line("  ✓ Sync {$label} done synchronously");
            } catch (\Throwable $e) {
                $this->error("  ✗ Sync {$label} failed: ".$e->getMessage());
            }

            return;
        }

        dispatch($job);
        $this->info("Dispatched Proxmox {$label} sync job to queue.");
    }
}

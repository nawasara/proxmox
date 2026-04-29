<?php

namespace Nawasara\Proxmox\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Nawasara\Vault\Facades\Vault;

/**
 * Proxmox VE API client.
 *
 * Auth: Authorization: PVEAPIToken={token_id}={token_secret}
 * Base URL pattern: {host}/api2/json/...
 *
 * Cluster-aware: a single credential covers every node in the cluster.
 * Endpoints traverse via /api2/json/nodes/{node}/... after fetching the
 * node list once.
 */
class ProxmoxClient
{
    public function isConfigured(): bool
    {
        return ! empty(Vault::get('proxmox', 'host'))
            && ! empty(Vault::get('proxmox', 'token_id'))
            && ! empty(Vault::get('proxmox', 'token_secret'));
    }

    /**
     * Build a configured HTTP client with PVEAPIToken auth header.
     */
    protected function api(): PendingRequest
    {
        $host = rtrim((string) Vault::get('proxmox', 'host'), '/');
        $tokenId = (string) Vault::get('proxmox', 'token_id');
        $tokenSecret = (string) Vault::get('proxmox', 'token_secret');
        $verifySsl = Vault::get('proxmox', 'verify_ssl');
        $verify = ! in_array(strtolower((string) $verifySsl), ['false', '0', 'no', ''], true);

        $req = Http::baseUrl($host.'/api2/json')
            ->withHeaders([
                'Authorization' => "PVEAPIToken={$tokenId}={$tokenSecret}",
            ])
            ->timeout((int) config('nawasara-proxmox.request_timeout', 15))
            ->acceptJson();

        if (! $verify) {
            $req = $req->withoutVerifying();
        }

        return $req;
    }

    /**
     * GET /api2/json/version → cluster version info.
     */
    public function getVersion(): ?array
    {
        $r = $this->api()->get('/version');
        return $r->successful() ? $r->json('data') : null;
    }

    /**
     * GET /api2/json/nodes → cluster-wide node list with resource summary.
     *
     * Each entry: { node, status, cpu, maxcpu, mem, maxmem, disk, maxdisk, uptime, ... }
     */
    public function getNodes(): array
    {
        $r = $this->api()->get('/nodes');
        return $r->successful() ? (array) $r->json('data') : [];
    }

    /**
     * GET /api2/json/cluster/resources?type=vm → list semua VM (qemu) +
     * container (lxc) di cluster sekaligus, beserta node_name + status +
     * resource. Ini one-shot sync, jauh lebih efisien daripada loop per-node.
     */
    public function getClusterVms(): array
    {
        $r = $this->api()->get('/cluster/resources', ['type' => 'vm']);
        return $r->successful() ? (array) $r->json('data') : [];
    }

    /**
     * GET single node detail (CPU model, OS, kernel, dst).
     */
    public function getNodeStatus(string $node): ?array
    {
        $r = $this->api()->get('/nodes/'.urlencode($node).'/status');
        return $r->successful() ? $r->json('data') : null;
    }

    /**
     * GET VM/container current status (lebih lengkap dari cluster resources).
     */
    public function getVmStatus(string $node, int $vmid, string $type = 'qemu'): ?array
    {
        $type = $type === 'lxc' ? 'lxc' : 'qemu';
        $r = $this->api()->get("/nodes/{$node}/{$type}/{$vmid}/status/current");
        return $r->successful() ? $r->json('data') : null;
    }

    /**
     * GET VM/container config (vCPU, memory, disk, network, dst).
     */
    public function getVmConfig(string $node, int $vmid, string $type = 'qemu'): ?array
    {
        $type = $type === 'lxc' ? 'lxc' : 'qemu';
        $r = $this->api()->get("/nodes/{$node}/{$type}/{$vmid}/config");
        return $r->successful() ? $r->json('data') : null;
    }

    /**
     * POST /nodes/{node}/{type}/{vmid}/status/{action}.
     *
     * Valid actions: start, stop, shutdown, reset, suspend, resume.
     * Returns the UPID task id on success, null on failure.
     */
    public function vmAction(string $node, int $vmid, string $action, string $type = 'qemu', array $params = []): ?string
    {
        $type = $type === 'lxc' ? 'lxc' : 'qemu';
        $action = strtolower($action);

        $allowed = ['start', 'stop', 'shutdown', 'reset', 'suspend', 'resume', 'reboot'];
        if (! in_array($action, $allowed, true)) {
            throw new \InvalidArgumentException("Unsupported VM action: {$action}");
        }

        // Proxmox expects form-encoded body for status mutations. Sending an
        // empty JSON or no body triggers `Not a HASH reference` from the
        // PVE::APIServer Perl backend, even on actions that take no params.
        $r = $this->api()->asForm()->post("/nodes/{$node}/{$type}/{$vmid}/status/{$action}", $params);

        if (! $r->successful()) {
            throw new \RuntimeException("Proxmox action {$action} failed: HTTP ".$r->status().' '.$r->body());
        }

        return $r->json('data');
    }

    /**
     * GET /nodes/{node}/tasks/{upid}/status — poll task progress.
     *
     * Returns { status: 'running'|'stopped', exitstatus: 'OK'|... }.
     */
    public function getTaskStatus(string $node, string $upid): ?array
    {
        $r = $this->api()->get("/nodes/{$node}/tasks/".urlencode($upid).'/status');
        return $r->successful() ? $r->json('data') : null;
    }

    /**
     * GET /nodes/{node}/tasks/{upid}/log — fetch task log lines.
     *
     * Returns a list of [{ n: int, t: string }, ...] where t is the line text.
     */
    public function getTaskLog(string $node, string $upid, int $start = 0, int $limit = 500): array
    {
        $r = $this->api()->get(
            "/nodes/{$node}/tasks/".urlencode($upid).'/log',
            ['start' => $start, 'limit' => $limit]
        );
        return $r->successful() ? (array) $r->json('data') : [];
    }

    /**
     * Wait for an async task to finish, polling every $intervalSeconds.
     *
     * Returns the final task status array, or null on timeout.
     */
    public function waitForTask(string $node, string $upid, int $timeoutSeconds = 60, int $intervalSeconds = 2): ?array
    {
        $deadline = time() + $timeoutSeconds;

        while (time() < $deadline) {
            $status = $this->getTaskStatus($node, $upid);

            if ($status && ($status['status'] ?? null) === 'stopped') {
                return $status;
            }

            sleep(max(1, $intervalSeconds));
        }

        return null;
    }

    /**
     * Cluster-wide version + node list. Lightweight — used as the smoke
     * check by Vault's "Test Connection" button.
     *
     * Returns ['success' => bool, 'message' => string].
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Field Proxmox belum lengkap di Vault.'];
        }

        try {
            $version = $this->api()->get('/version');

            if ($version->status() === 401) {
                return ['success' => false, 'message' => 'Authentication ditolak — periksa token_id / token_secret.'];
            }

            if (! $version->successful()) {
                return ['success' => false, 'message' => 'Connect berhasil tapi /version gagal: HTTP '.$version->status().' '.$version->body()];
            }

            $data = $version->json('data');
            $versionStr = $data['version'] ?? 'unknown';
            $release = $data['release'] ?? '';

            // Fetch node list — proves cluster API juga responsif
            $nodes = $this->api()->get('/nodes');
            $nodeCount = $nodes->successful() ? count((array) $nodes->json('data')) : 0;

            return [
                'success' => true,
                'message' => "Connect ke Proxmox berhasil. Version: {$versionStr} (build {$release}). Cluster punya {$nodeCount} node.",
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Tidak bisa connect: '.$e->getMessage()];
        }
    }
}

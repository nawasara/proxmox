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

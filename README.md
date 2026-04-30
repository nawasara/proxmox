# Nawasara Proxmox

Proxmox VE management for the Nawasara superapp framework — VM and LXC inventory, lifecycle control (start/stop/shutdown/reboot), snapshot management, live resource metrics, and a one-click console deep link, all surfaced from a local DB snapshot for speed and mutated through queue jobs for auditability.

## Features

- **Cluster-aware inventory** — single API token covers every node in a PVE cluster; one-shot fetch via `/cluster/resources?type=vm`
- **VM / LXC list** — paginated, filterable by node, status, type, template; running/stopped/paused chips with live counts
- **Lifecycle actions** — start, graceful shutdown, force stop, reboot, with `wire:confirm` dialogs that distinguish graceful from force-stop
- **Auto re-sync** — every successful action chains a `SyncProxmoxVmsJob` so the UI reflects real Proxmox state (uptime, mem usage, locks) immediately after
- **Live status badges** — in-row spinner while a queued/running action is in flight; red "X failed" hint for 5 minutes after a failure
- **Task log viewer** — terminal-styled modal that streams the Proxmox task log (`/nodes/{n}/tasks/{upid}/log`) for any past action
- **Detail modal** with:
  - Live (instant) CPU + Memory percentages from `/status/current`
  - 1h CPU and Memory sparklines from RRD AVERAGE (peak / avg supporting metrics)
  - Network interfaces parsed (MAC, bridge, firewall flag) from `getVmConfig()`
  - Disks listed with raw config string per device
  - OS type, boot order, description
- **Snapshot management** — list, create (with optional vmstate / RAM capture for qemu), rollback, delete; UPID-tracked with up-to-10-minute timeout
- **Console deep link** — opens the existing PVE web noVNC for the VM in a new tab (admins authenticate with their own browser session — API tokens can't issue cookie-bound vncproxy tickets)
- **Cluster overview** — node summary cards (CPU / RAM / storage totals + per-node detail), polls every 30s idle, every 4s while a sync is in flight
- **Sync info bar** showing last successful sync time, status counts, and a link to the audit log

The package follows the DB-cache + queue pattern from `nawasara/sync`: reads come from local snapshot tables; writes dispatch queue jobs that hit the Proxmox API and update the snapshot via content-hash diffing.

## Installation

```bash
composer require nawasara/proxmox
php artisan migrate
php artisan db:seed --class="Nawasara\Proxmox\Database\Seeders\PermissionSeeder" --force
```

The package is auto-discovered by Laravel — no manual provider registration required.

## Proxmox API Token Setup

The package authenticates with a **PVE API Token**, which can be revoked or scope-restricted from the Proxmox UI without touching the underlying user.

### 1. Sign in to the Proxmox web UI

Open `https://your-proxmox-host:8006` and sign in as `root@pam` (or any user with `Sys.Audit` + `VM.*` permissions on `/`).

### 2. Open API Tokens

Navigate to **Datacenter → Permissions → API Tokens**.

### 3. Create a token

Click **Add**:

- **User** — choose the user that owns the token (e.g. `root@pam`)
- **Token ID** — short identifier (e.g. `nawasara`)
- **Privilege Separation** — uncheck if you want the token to inherit the user's permissions, OR leave checked and assign granular permissions separately (recommended for least-privilege)
- **Expire** — leave empty for permanent

Click **Add**. The dialog displays the token secret **once** — copy it now.

The token is identified by `{user}@{realm}!{tokenid}` (e.g. `root@pam!nawasara`).

### 4. Grant permissions

If you used Privilege Separation, the token has *no* permissions until you grant them:

**Datacenter → Permissions → Add → API Token Permission**:

| Path | Role | Used for |
|---|---|---|
| `/` | `PVEAuditor` | Read cluster, nodes, VM list, status |
| `/vms/<vmid>` (or `/vms`) | `PVEVMAdmin` | Lifecycle actions, snapshots |
| `/nodes/<node>` | `PVEAuditor` | Per-node detail, RRD data |

For a quick start: grant `PVEAdmin` on `/` to a single dedicated token, then narrow it down later.

### 5. Self-signed certificates

If your Proxmox cluster uses a self-signed certificate (default on fresh installs), set **Verify SSL** to `false` in the Vault configuration below. For production, install a trusted certificate and keep verification on.

## Storing credentials in Vault

1. Open Nawasara → `/nawasara-vault`
2. Choose the **Proxmox** group
3. Fill in:
   - **Host** — `https://pve.example.go.id:8006` (no trailing slash, include the port)
   - **Token ID** — full identifier `user@realm!tokenname`
   - **Token Secret** — the value displayed once in step 3
   - **Verify SSL** — `true` if your PVE has a trusted cert, `false` for self-signed
4. Click **Test Connection** — should respond with cluster version + node count
5. Save

The package picks up credentials from Vault automatically.

## Verification

1. **Sidebar** — "Proxmox" workspace appears with "Virtual Machines" and "Nodes" entries
2. **Nodes page** — cluster summary cards (Nodes, VMs, vCPU, Memory, Storage) populate after the first sync
3. **VMs page** — list shows every VM and LXC across the cluster
4. **Click a row → Detail** — sparklines render, network interfaces and disks listed, snapshot section visible (if you have `proxmox.vm.snapshot`)
5. **Click Start / Shutdown** on a non-template VM — status badge shows a spinner; the table auto-refreshes when the task completes
6. **Click "Lihat Log"** — modal shows the Proxmox task log for the most recent action

## Error handling reference

| Symptom | Cause | Fix |
|---|---|---|
| `HTTP 401` on test connection | Token ID or secret typo | Re-paste from PVE; full ID format is `user@realm!tokenname` |
| `HTTP 403` on lifecycle action | Token lacks `VM.PowerMgmt` on `/vms/<id>` | Grant `PVEVMAdmin` on `/vms` (or specific path) |
| `HTTP 500: Not a HASH reference` | Mutation sent without form body (legacy bug) | Already handled — client uses `asForm()` for all POST mutations |
| `cURL: SSL certificate problem` | Self-signed cert + `verify_ssl=true` | Set Vault `verify_ssl` to `false`, or install a trusted cert |
| Action stuck at "Starting…" forever | Task didn't appear in `/tasks/{upid}/status` | Check Proxmox `/var/log/pveproxy/access.log`; raise the job `timeout` if the task is genuinely slow |
| VMs missing from list | Token can't see them | Token needs at least `PVEAuditor` on the path containing the VMs (`/vms` or `/pool/<poolname>`) |

## Permissions

| Permission | Description |
|---|---|
| `proxmox.node.view` | View cluster + node list |
| `proxmox.vm.view` | View VM/LXC list and detail |
| `proxmox.vm.lifecycle` | Start, stop, shutdown, reboot |
| `proxmox.vm.snapshot` | Create, rollback, delete snapshots |
| `proxmox.vm.console` | Open the noVNC console |
| `proxmox.sync.execute` | Trigger a manual cluster re-sync |

All permissions are auto-assigned to the `developer` role by the seeder.

## Future cross-link with iTop

The `nawasara_proxmox_vms` table reserves an `itop_server_id` column (nullable, indexed). When the `nawasara/itop` integration ships, the sync job will populate this with the matched `ItopServer.id` (where `finalclass = VirtualMachine`) so a single VM can be navigated from either side.

## Author

**Pringgo J. Saputro** &lt;odyinggo@gmail.com&gt;

## License

MIT

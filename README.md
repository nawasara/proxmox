# Nawasara Proxmox

Proxmox VE management for the Nawasara superapp framework: VM and LXC inventory, lifecycle control (start/stop/shutdown/reboot), snapshot management, live resource metrics, and a one-click console deep link. Everything is surfaced from a local DB snapshot for speed and mutated through queue jobs for auditability.

## Features

- **Cluster-aware inventory.** A single API token covers every node in a PVE cluster; one-shot fetch via `/cluster/resources?type=vm`.
- **VM / LXC list.** Paginated, filterable by node, status, type, and template; running/stopped/paused chips with live counts.
- **Lifecycle actions.** Start, graceful shutdown, force stop, and reboot, with `wire:confirm` dialogs that distinguish graceful from force-stop.
- **Auto re-sync.** Every successful action chains a `SyncProxmoxVmsJob` so the UI reflects real Proxmox state (uptime, mem usage, locks) immediately after.
- **Live status badges.** An in-row spinner while a queued/running action is in flight; a red "X failed" hint for 5 minutes after a failure.
- **Task log viewer.** A terminal-styled modal that streams the Proxmox task log (`/nodes/{n}/tasks/{upid}/log`) for any past action.
- **Detail modal** with:
  - Live (instant) CPU and Memory percentages from `/status/current`
  - 1h CPU and Memory sparklines from RRD AVERAGE (peak / avg supporting metrics)
  - Network interfaces parsed (MAC, bridge, firewall flag) from `getVmConfig()`
  - Disks listed with the raw config string per device
  - OS type, boot order, description
- **Snapshot management.** List, create (with optional vmstate / RAM capture for qemu), rollback, and delete; UPID-tracked with an up-to-10-minute timeout.
- **Console deep link.** Opens the existing PVE web noVNC for the VM in a new tab. Admins authenticate with their own browser session, because API tokens cannot issue cookie-bound vncproxy tickets.
- **Cluster overview.** Node summary cards (CPU / RAM / storage totals plus per-node detail); polls every 30s idle, every 4s while a sync is in flight.
- **Sync info bar** showing the last successful sync time, status counts, and a link to the audit log.

The package follows the DB-cache plus queue pattern from `nawasara/sync`: reads come from local snapshot tables; writes dispatch queue jobs that hit the Proxmox API and update the snapshot via content-hash diffing.

## Capacity alerts

Runs hourly (`nawasara-proxmox:check-capacity`) and warns before a disk fills
up. It was added after the production database went down because its disk was
full, with no warning at all. The data had been syncing every 15 minutes for
months; nothing was reading it and saying "this is about to become a problem."

It fires only when **both** hold: usage is past the threshold, and the free
space left is genuinely small (default 20 GB). Percentage alone misleads on
large disks. One production machine sits at 84.6% with 151 GB free, and an alert
that fires when nothing needs doing is the fastest way to teach people to ignore
it.

It resolves itself once space frees up, so a badge that stays lit never buries
the alert that is actually new.

### QEMU VMs are not covered

Proxmox only reports in-guest disk usage for **LXC**. For QEMU the API returns
**0** unless `qemu-guest-agent` is installed, and in Ponorogo all 20 QEMU VMs
report 0.

Those machines are **skipped, not treated as 0%**. Treating them as empty would
paint the dashboard green for machines whose state is simply unknown, the same
false confidence that let the outage through. The skipped count is logged every
run so the gap stays visible.

Install the guest agent inside a VM and it joins the check automatically.

Thresholds are `.env`-tunable: `PROXMOX_DISK_WARNING`, `PROXMOX_DISK_CRITICAL`,
`PROXMOX_DISK_MIN_FREE_GB`, `PROXMOX_MEM_WARNING`, `PROXMOX_MEM_CRITICAL`.

Guide (Indonesian): [`docs/panduan/pemantauan-kapasitas.md`](../../docs/panduan/pemantauan-kapasitas.md)

## Installation

```bash
composer require nawasara/proxmox
php artisan migrate
php artisan db:seed --class="Nawasara\Proxmox\Database\Seeders\PermissionSeeder" --force
```

The package is auto-discovered by Laravel, so no manual provider registration is required.

## Proxmox API Token Setup

The package authenticates with a **PVE API Token**, which can be revoked or scope-restricted from the Proxmox UI without touching the underlying user.

### 1. Sign in to the Proxmox web UI

Open `https://your-proxmox-host:8006` and sign in as `root@pam` (or any user with `Sys.Audit` plus `VM.*` permissions on `/`).

### 2. Open API Tokens

Navigate to **Datacenter → Permissions → API Tokens**.

### 3. Create a token

Click **Add**:

- **User.** Choose the user that owns the token (e.g. `root@pam`).
- **Token ID.** A short identifier (e.g. `nawasara`).
- **Privilege Separation.** Uncheck if you want the token to inherit the user's permissions, or leave it checked and assign granular permissions separately (recommended for least-privilege).
- **Expire.** Leave empty for permanent.

Click **Add**. The dialog displays the token secret **once**, so copy it now.

The token is identified by `{user}@{realm}!{tokenid}` (e.g. `root@pam!nawasara`).

### 4. Grant permissions

If you used Privilege Separation, the token has *no* permissions until you grant them:

**Datacenter → Permissions → Add → API Token Permission**:

| Path | Role | Used for |
|---|---|---|
| `/` | `PVEAuditor` | Read cluster, nodes, VM list, status |
| `/vms/<vmid>` (or `/vms`) | `PVEVMAdmin` | Lifecycle actions, snapshots |
| `/nodes/<node>` | `PVEAuditor` | Per-node detail, RRD data |

For a quick start, grant `PVEAdmin` on `/` to a single dedicated token, then narrow it down later.

### 5. Self-signed certificates

If your Proxmox cluster uses a self-signed certificate (the default on fresh installs), set **Verify SSL** to `false` in the Vault configuration below. For production, install a trusted certificate and keep verification on.

## Storing credentials in Vault

1. Open Nawasara → `/nawasara-vault`
2. Choose the **Proxmox** group
3. Fill in:
   - **Host.** `https://pve.example.go.id:8006` (no trailing slash, include the port)
   - **Token ID.** The full identifier `user@realm!tokenname`
   - **Token Secret.** The value displayed once in step 3
   - **Verify SSL.** `true` if your PVE has a trusted cert, `false` for self-signed
4. Click **Test Connection**. It should respond with the cluster version plus node count.
5. Save

The package picks up credentials from Vault automatically.

## Verification

1. **Sidebar.** The "Proxmox" workspace appears with "Virtual Machines" and "Nodes" entries.
2. **Nodes page.** The cluster summary cards (Nodes, VMs, vCPU, Memory, Storage) populate after the first sync.
3. **VMs page.** The list shows every VM and LXC across the cluster.
4. **Click a row → Detail.** Sparklines render, network interfaces and disks are listed, and the snapshot section is visible (if you have `proxmox.vm.snapshot`).
5. **Click Start / Shutdown** on a non-template VM. The status badge shows a spinner; the table auto-refreshes when the task completes.
6. **Click "Lihat Log".** The modal shows the Proxmox task log for the most recent action.

## Error handling reference

| Symptom | Cause | Fix |
|---|---|---|
| `HTTP 401` on test connection | Token ID or secret typo | Re-paste from PVE; the full ID format is `user@realm!tokenname` |
| `HTTP 403` on lifecycle action | Token lacks `VM.PowerMgmt` on `/vms/<id>` | Grant `PVEVMAdmin` on `/vms` (or the specific path) |
| `HTTP 500: Not a HASH reference` | Mutation sent without a form body (legacy bug) | Already handled; the client uses `asForm()` for all POST mutations |
| `cURL: SSL certificate problem` | Self-signed cert plus `verify_ssl=true` | Set Vault `verify_ssl` to `false`, or install a trusted cert |
| Action stuck at "Starting..." forever | Task did not appear in `/tasks/{upid}/status` | Check Proxmox `/var/log/pveproxy/access.log`; raise the job `timeout` if the task is genuinely slow |
| VMs missing from the list | Token cannot see them | The token needs at least `PVEAuditor` on the path containing the VMs (`/vms` or `/pool/<poolname>`) |

## Permissions

| Permission | Description |
|---|---|
| `proxmox.node.view` | View cluster and node list |
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

<!-- v0.4.1: re-released because the v0.4.0 dist zip on Packagist held the code
     from before the console. The commit reference was right, the contents were
     not, and composer installed it without any complaint. A NEW version number
     is what fixes this; re-publishing a tag with the same name does not refresh
     the zip. -->

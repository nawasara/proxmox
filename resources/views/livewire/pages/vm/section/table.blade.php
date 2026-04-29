<div>
    {{-- Sync info bar --}}
    <div class="mb-3 flex items-center justify-between text-xs text-gray-500 dark:text-neutral-400">
        <div class="flex items-center gap-3">
            @if ($this->lastSyncedAt)
                <span><x-lucide-clock class="size-3 inline" /> Last sync: {{ $this->lastSyncedAt }}</span>
            @else
                <span class="text-yellow-600">Belum pernah di-sync. Klik "Sync Sekarang".</span>
            @endif

            @php $sc = $this->statusCounts; @endphp
            @if (! empty($sc))
                <span>·</span>
                @if (($sc['running'] ?? 0) > 0)
                    <span class="text-green-600">{{ $sc['running'] }} running</span>
                @endif
                @if (($sc['stopped'] ?? 0) > 0)
                    <span class="text-gray-500">{{ $sc['stopped'] }} stopped</span>
                @endif
                @if (($sc['paused'] ?? 0) > 0)
                    <span class="text-yellow-600">{{ $sc['paused'] }} paused</span>
                @endif
            @endif
        </div>
        <a href="{{ url('admin/sync/jobs?service=proxmox') }}" wire:navigate class="text-blue-600 hover:underline">
            Lihat Sync Jobs →
        </a>
    </div>

    <x-nawasara-ui::filter-bar searchPlaceholder="Cari nama, vmid, deskripsi..." searchModel="search">
        <x-nawasara-ui::filter-dropdown label="Node" model="nodeFilter" :items="$this->nodeOptions" />
        <x-nawasara-ui::filter-dropdown label="Status" model="statusFilter"
            :items="['all' => 'Semua Status', 'running' => 'Running', 'stopped' => 'Stopped', 'paused' => 'Paused']" />
        <x-nawasara-ui::filter-dropdown label="Type" model="typeFilter"
            :items="['all' => 'VM + Container', 'qemu' => 'VM (qemu)', 'lxc' => 'Container (LXC)']" />
        <x-nawasara-ui::filter-dropdown label="Template" model="templateFilter"
            :items="['hide' => 'Sembunyikan template', 'only' => 'Hanya template', 'all' => 'Tampilkan semua']" />

        <x-slot:actions>
            <x-nawasara-ui::button color="neutral" variant="outline" size="sm" wire:click="refresh">
                <x-slot:icon>
                    <x-lucide-refresh-cw wire:loading.class="animate-spin" wire:target="refresh" />
                </x-slot:icon>
                Sync Sekarang
            </x-nawasara-ui::button>
        </x-slot:actions>

        <x-slot:chips>
            @if ($nodeFilter)
                <x-nawasara-ui::filter-chip label="Node: {{ $nodeFilter }}" model="nodeFilter" />
            @endif
            @if ($statusFilter)
                <x-nawasara-ui::filter-chip label="Status: {{ ucfirst($statusFilter) }}" model="statusFilter" />
            @endif
            @if ($typeFilter)
                <x-nawasara-ui::filter-chip label="Type: {{ $typeFilter }}" model="typeFilter" />
            @endif
            @if ($search)
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            @endif
        </x-slot:chips>
    </x-nawasara-ui::filter-bar>

    <x-nawasara-ui::table
        :headers="['VMID', 'Name', 'Type', 'Node', 'Status', 'CPU', 'Memory', 'Disk', 'Uptime', '']"
        :title="'VMs ('.$this->vms->total().' total)'">
        <x-slot:table>
            @forelse ($this->vms as $vm)
                <tr wire:key="vm-{{ $vm->id }}">
                    <td class="px-4 py-3 whitespace-nowrap text-xs font-mono text-gray-600 dark:text-neutral-400">
                        {{ $vm->vmid }}
                    </td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-neutral-200">
                        <div class="flex items-center gap-2">
                            <span>{{ $vm->name ?? '—' }}</span>
                            @if ($vm->template)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-400 uppercase">template</span>
                            @endif
                            @if ($vm->isLocked())
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400 uppercase" title="Locked: {{ $vm->lock }}">
                                    {{ $vm->lock }}
                                </span>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs">
                        @if ($vm->vm_type === 'qemu')
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">VM</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">LXC</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs font-mono text-gray-600 dark:text-neutral-400">
                        {{ $vm->node_name }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                        @if ($vm->status === 'running')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                <span class="size-1.5 rounded-full bg-green-500"></span> Running
                            </span>
                        @elseif ($vm->status === 'stopped')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-400">
                                Stopped
                            </span>
                        @elseif ($vm->status === 'paused')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                Paused
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                {{ ucfirst($vm->status ?? 'unknown') }}
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600 dark:text-neutral-400 font-mono">
                        {{ $vm->cpu_count ?? '—' }}
                        @if ($vm->cpu_usage !== null && $vm->isRunning())
                            <span class="text-gray-400">({{ round($vm->cpu_usage * 100, 1) }}%)</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600 dark:text-neutral-400 font-mono">
                        @if ($vm->mem_total)
                            {{ number_format($vm->mem_total / 1024 / 1024 / 1024, 1) }}G
                            @if ($vm->isRunning() && $vm->memUsagePercent() !== null)
                                <span class="text-gray-400">({{ $vm->memUsagePercent() }}%)</span>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600 dark:text-neutral-400 font-mono">
                        @if ($vm->disk_total)
                            {{ number_format($vm->disk_total / 1024 / 1024 / 1024, 0) }}G
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500 dark:text-neutral-500">
                        @if ($vm->uptime && $vm->isRunning())
                            @php
                                $d = floor($vm->uptime / 86400);
                                $h = floor(($vm->uptime % 86400) / 3600);
                            @endphp
                            {{ $d > 0 ? $d.'d ' : '' }}{{ $h }}h
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                        @php
                            $items = [
                                ['type' => 'click', 'label' => 'Detail', 'wire:click' => 'openDetail('.$vm->id.')', 'modal' => 'proxmox-vm-detail', 'icon' => 'lucide-eye', 'permission' => 'proxmox.vm.view'],
                            ];
                            if (! $vm->template) {
                                if ($vm->status !== 'running') {
                                    $items[] = ['type' => 'click', 'label' => 'Start', 'wire:click' => "vmAction({$vm->id}, 'start')", 'icon' => 'lucide-play', 'permission' => 'proxmox.vm.lifecycle', 'confirm' => "Start VM {$vm->name} (#{$vm->vmid})?"];
                                }
                                if ($vm->status === 'running') {
                                    $items[] = ['type' => 'click', 'label' => 'Restart', 'wire:click' => "vmAction({$vm->id}, 'restart')", 'icon' => 'lucide-rotate-cw', 'permission' => 'proxmox.vm.lifecycle', 'confirm' => "Reboot VM {$vm->name} (#{$vm->vmid})?\n\nGuest agent atau init container harus aktif untuk reboot graceful."];
                                    $items[] = ['type' => 'click', 'label' => 'Shutdown', 'wire:click' => "vmAction({$vm->id}, 'shutdown')", 'icon' => 'lucide-power', 'permission' => 'proxmox.vm.lifecycle', 'confirm' => "Graceful shutdown VM {$vm->name} (#{$vm->vmid})?\n\nSama seperti tekan tombol power di komputer fisik."];
                                    $items[] = ['type' => 'click', 'label' => 'Stop (force)', 'wire:click' => "vmAction({$vm->id}, 'stop')", 'icon' => 'lucide-square', 'permission' => 'proxmox.vm.lifecycle', 'confirm' => "FORCE STOP VM {$vm->name} (#{$vm->vmid})?\n\nIni akan memutus power tanpa shutdown graceful — data yg belum di-flush bisa hilang. Pakai opsi Shutdown jika memungkinkan."];
                                }
                            }
                        @endphp
                        <x-nawasara-ui::dropdown-menu-action :id="$vm->id" :items="$items" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">
                        @if ($this->lastSyncedAt === null)
                            Database masih kosong. Klik <strong>Sync Sekarang</strong> untuk fetch dari Proxmox.
                        @else
                            Tidak ada VM yang cocok filter ini.
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $this->vms->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>

    {{-- Detail Modal --}}
    <x-nawasara-ui::modal id="proxmox-vm-detail" maxWidth="2xl" :title="$this->detail ? ($this->detail->name ?? 'VM '.$this->detail->vmid) : ''">
        @if ($this->detail)
            @php $v = $this->detail; @endphp
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div><span class="text-gray-500">VMID:</span> <span class="font-mono">{{ $v->vmid }}</span></div>
                <div><span class="text-gray-500">Type:</span> <span>{{ $v->vm_type === 'qemu' ? 'Virtual Machine' : 'LXC Container' }}</span></div>
                <div><span class="text-gray-500">Node:</span> <span class="font-mono">{{ $v->node_name }}</span></div>
                <div><span class="text-gray-500">Status:</span> <span class="font-medium">{{ ucfirst($v->status) }}</span></div>
                <div><span class="text-gray-500">vCPU:</span> {{ $v->cpu_count ?? '—' }}</div>
                <div><span class="text-gray-500">CPU usage:</span>
                    {{ $v->cpu_usage !== null ? round($v->cpu_usage * 100, 2).'%' : '—' }}
                </div>
                <div><span class="text-gray-500">Memory total:</span>
                    {{ $v->mem_total ? number_format($v->mem_total / 1024 / 1024 / 1024, 2).' GB' : '—' }}
                </div>
                <div><span class="text-gray-500">Memory used:</span>
                    {{ $v->mem_used ? number_format($v->mem_used / 1024 / 1024 / 1024, 2).' GB' : '—' }}
                    @if ($v->memUsagePercent() !== null)
                        <span class="text-gray-400">({{ $v->memUsagePercent() }}%)</span>
                    @endif
                </div>
                <div><span class="text-gray-500">Disk total:</span>
                    {{ $v->disk_total ? number_format($v->disk_total / 1024 / 1024 / 1024, 0).' GB' : '—' }}
                </div>
                <div><span class="text-gray-500">Uptime:</span>
                    @if ($v->uptime)
                        @php
                            $d = floor($v->uptime / 86400);
                            $h = floor(($v->uptime % 86400) / 3600);
                            $m = floor(($v->uptime % 3600) / 60);
                        @endphp
                        {{ $d > 0 ? $d.'d ' : '' }}{{ $h }}h {{ $m }}m
                    @else
                        —
                    @endif
                </div>
                <div><span class="text-gray-500">Template:</span> {{ $v->template ? 'Ya' : 'Tidak' }}</div>
                <div><span class="text-gray-500">Lock:</span> {{ $v->lock ?? '—' }}</div>

                @if (! empty($v->tags))
                    <div class="col-span-2">
                        <span class="text-gray-500">Tags:</span>
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach ($v->tags as $tag)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300">{{ $tag }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="col-span-2 mt-2 pt-3 border-t border-gray-200 dark:border-neutral-700">
                    <span class="text-gray-500">Last synced:</span>
                    {{ $v->last_synced_at?->diffForHumans() ?? '—' }}
                </div>
            </div>
        @endif
        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" variant="outline" wire:click="closeDetail">Tutup</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>

<div>
    <x-nawasara-ui::page.card>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-neutral-800 dark:text-neutral-100">
                Alamat terpakai
            </h2>

            <div class="flex flex-wrap items-center gap-2">
                <x-nawasara-ui::search-input
                    wire:model.live.debounce.400ms="search"
                    placeholder="Cari IP, VM, MAC, node…" />

                <x-nawasara-ui::filter-panel>
                    <x-nawasara-ui::filter-group label="Subnet">
                        <x-nawasara-ui::form.select
                            wire:model.live="subnet"
                            :options="$this->subnetOptions"
                            placeholder="Semua subnet" />
                    </x-nawasara-ui::filter-group>

                    <x-nawasara-ui::filter-group label="Asal data">
                        <x-nawasara-ui::form.select
                            wire:model.live="source"
                            :options="['config' => 'Dari Proxmox', 'manual' => 'Diisi manual']"
                            placeholder="Semua asal" />
                    </x-nawasara-ui::filter-group>
                </x-nawasara-ui::filter-panel>
            </div>
        </div>

        @if ($this->rows->isEmpty())
            <x-nawasara-ui::empty-state
                icon="lucide-search-x"
                title="Tidak ada alamat yang cocok"
                description="Ubah kata kunci atau saringannya." />
        @else
            <x-nawasara-ui::table :headers="['IP', 'VM', 'Node', 'NIC', 'MAC', 'Subnet', 'Asal']">
                <x-slot:table>
                @foreach ($this->rows as $row)
                    <tr wire:key="ip-{{ $row->id }}">
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-sm font-medium text-neutral-800 dark:text-neutral-100">
                                    {{ $row->ip }}
                                </span>
                                {{--
                                    Bentrok ditandai di baris alamatnya sendiri.
                                    Ia jarang disadari sampai salah satu layanan
                                    mati, jadi tandanya harus ada di tempat mata
                                    pertama kali jatuh.
                                --}}
                                @if (isset($this->conflictingIps[$row->ip]))
                                    <x-nawasara-ui::badge color="danger">Bentrok</x-nawasara-ui::badge>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="text-sm text-neutral-800 dark:text-neutral-100">
                                {{ $row->vm_name ?? '—' }}
                            </span>
                            @if ($row->vmid)
                                <span class="ml-1 text-xs text-neutral-500 dark:text-neutral-400">
                                    #{{ $row->vmid }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $row->node_name ?? '—' }}
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs text-neutral-700 dark:text-neutral-200">
                            {{ $row->nic ?? '—' }}
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs text-neutral-600 dark:text-neutral-300">
                            {{ $row->mac ?? '—' }}
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs text-neutral-600 dark:text-neutral-300">
                            {{ $row->subnet?->cidr ?? '—' }}
                        </td>
                        <td class="px-4 py-2.5">
                            @if ($row->isManual())
                                <x-nawasara-ui::badge color="info">
                                    Manual{{ $row->filled_by ? ' · '.$row->filled_by : '' }}
                                </x-nawasara-ui::badge>
                            @else
                                <x-nawasara-ui::badge color="neutral">Proxmox</x-nawasara-ui::badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </x-slot:table>
            </x-nawasara-ui::table>

            <div class="mt-4">
                {{ $this->rows->links() }}
            </div>
        @endif
    </x-nawasara-ui::page.card>
</div>

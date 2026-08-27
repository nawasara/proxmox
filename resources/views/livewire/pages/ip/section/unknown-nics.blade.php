<div>
    <x-nawasara-ui::page.card>
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-neutral-800 dark:text-neutral-100">
                    Menunggu diisi
                </h2>
                <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">
                    Proxmox tidak menyimpan alamat VM QEMU di konfigurasinya. MAC dan bridge
                    di bawah adalah penuntun untuk mengisinya.
                </p>
            </div>
            <x-nawasara-ui::badge :color="count($this->nics) > 0 ? 'warning' : 'success'">
                {{ count($this->nics) }}
            </x-nawasara-ui::badge>
        </div>

        @if (count($this->nics) === 0)
            <x-nawasara-ui::empty-state
                icon="lucide-circle-check"
                title="Semua NIC sudah diketahui alamatnya"
                description="Angka alamat bebas kini dapat dipercaya sepenuhnya." />
        @else
            <x-nawasara-ui::table :headers="['VM', 'Node', 'NIC', 'MAC', 'Bridge', 'Subnet biasanya', '']">
                <x-slot:table>
                @foreach ($this->nics as $nic)
                    <tr wire:key="nic-{{ $nic->id }}">
                        <td class="px-4 py-2.5">
                            <span class="text-sm text-neutral-800 dark:text-neutral-100">
                                {{ $nic->vm_name ?? '—' }}
                            </span>
                            <span class="ml-1 text-xs text-neutral-500 dark:text-neutral-400">
                                #{{ $nic->vmid }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $nic->node_name }}
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs text-neutral-700 dark:text-neutral-200">
                            {{ $nic->nic }}
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs text-neutral-600 dark:text-neutral-300">
                            {{ $nic->mac ?? '—' }}
                        </td>
                        <td class="px-4 py-2.5 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $nic->bridge ?? '—' }}
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs text-neutral-600 dark:text-neutral-300">
                            {{ $nic->suggestedSubnet?->cidr ?? '—' }}
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            @can('proxmox.ip.edit')
                                <x-nawasara-ui::button
                                    size="sm"
                                    color="primary"
                                    x-on:click="$dispatch('open-modal', { id: 'isi-ip', loading: true })"
                                    wire:click="edit({{ $nic->id }})">
                                    Isi IP
                                </x-nawasara-ui::button>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </x-slot:table>
            </x-nawasara-ui::table>
        @endif
    </x-nawasara-ui::page.card>

    <x-nawasara-ui::modal id="isi-ip" title="Isi alamat IP">
        @if ($this->editing)
            <div class="space-y-4">
                <div class="rounded-lg bg-neutral-50 p-3 text-sm dark:bg-neutral-800">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">VM</span>
                            <p class="text-neutral-800 dark:text-neutral-100">
                                {{ $this->editing->vm_name ?? '—' }} #{{ $this->editing->vmid }}
                            </p>
                        </div>
                        <div>
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">NIC</span>
                            <p class="font-mono text-neutral-800 dark:text-neutral-100">
                                {{ $this->editing->nic }}
                            </p>
                        </div>
                        <div>
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">MAC</span>
                            <p class="font-mono text-xs text-neutral-800 dark:text-neutral-100">
                                {{ $this->editing->mac ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <span class="text-xs text-neutral-500 dark:text-neutral-400">Bridge</span>
                            <p class="text-neutral-800 dark:text-neutral-100">
                                {{ $this->editing->bridge ?? '—' }}
                            </p>
                        </div>
                    </div>
                </div>

                @if (count($this->suggestions) > 0)
                    <div>
                        <p class="mb-1.5 text-xs font-medium text-neutral-600 dark:text-neutral-300">
                            Alamat bebas di {{ $this->editing->suggestedSubnet?->cidr }} — klik untuk memakai
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($this->suggestions as $saran)
                                <button
                                    type="button"
                                    wire:click="use('{{ $saran }}')"
                                    class="rounded border border-neutral-300 bg-white px-2 py-1 font-mono text-xs text-neutral-700 hover:border-sky-500 hover:text-sky-600 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:border-sky-400 dark:hover:text-sky-400">
                                    {{ $saran }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div>
                    <x-nawasara-ui::form.input
                        type="text"
                        label="Alamat IP"
                        wire:model="ip"
                        placeholder="103.109.206.50" />
                </div>

                <div>
                    <x-nawasara-ui::form.textarea
                        label="Catatan"
                        wire:model="note"
                        :rows="2"
                        hint="Opsional — dari mana alamat ini diketahui." />
                </div>
            </div>
        @else
            <x-nawasara-ui::loading />
        @endif

        <x-slot name="footer">
            <x-nawasara-ui::button
                color="neutral"
                x-on:click="$dispatch('close-modal', 'isi-ip')">
                Batal
            </x-nawasara-ui::button>

            {{--
                wire:click, BUKAN tombol submit di dalam <form>.
                Modal nawasara-ui merender slot footer DI LUAR div konten,
                sehingga tombol submit di sini lolos dari form-nya dan
                wire:submit tidak pernah menyala.
            --}}
            <x-nawasara-ui::button color="primary" wire:click="save">
                Simpan
            </x-nawasara-ui::button>
        </x-slot>
    </x-nawasara-ui::modal>
</div>

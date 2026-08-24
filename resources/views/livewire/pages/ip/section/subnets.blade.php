<div>
    <x-nawasara-ui::page-header
        title="Inventori IP"
        description="Alamat yang terpakai dan yang masih bebas di tiap kolam, dibaca dari konfigurasi Proxmox."
        :count="count($this->summaries)">
    </x-nawasara-ui::page-header>

    @if (count($this->summaries) === 0)
        <x-nawasara-ui::page.card>
            <x-nawasara-ui::empty-state
                icon="lucide-network"
                title="Belum ada data subnet"
                description="Jalankan sinkronisasi Proxmox terlebih dahulu — subnet dibaca dari bridge tiap node." />
        </x-nawasara-ui::page.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->summaries as $s)
                @php($subnet = $s['subnet'])
                <x-nawasara-ui::page.card>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-sm font-semibold text-neutral-800 dark:text-neutral-100">
                                    {{ $subnet->cidr }}
                                </span>
                                <x-nawasara-ui::badge :color="$subnet->is_public ? 'warning' : 'neutral'">
                                    {{ $subnet->is_public ? 'Publik' : 'Lokal' }}
                                </x-nawasara-ui::badge>
                            </div>
                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                {{ $subnet->bridge ?? '—' }}
                                @if ($subnet->gateway)
                                    · gw {{ $subnet->gateway }}
                                @endif
                            </p>
                        </div>

                        <x-nawasara-ui::icon-button
                            icon="lucide-list"
                            tooltip="Lihat alamat bebas"
                            placement="left"
                            wire:click="toggle({{ $subnet->id }})" />
                    </div>

                    <div class="mt-4 flex items-baseline gap-2">
                        <span @class([
                            'text-2xl font-semibold',
                            'text-rose-600 dark:text-rose-400' => $s['free'] <= 5,
                            'text-amber-600 dark:text-amber-400' => $s['free'] > 5 && $s['free'] <= 20,
                            'text-emerald-600 dark:text-emerald-400' => $s['free'] > 20,
                        ])>{{ $s['free'] }}</span>
                        <span class="text-sm text-neutral-500 dark:text-neutral-400">
                            bebas dari {{ $s['usable'] }}
                        </span>
                    </div>

                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
                        @php($pakai = $s['usable'] > 0 ? min(100, round((($s['used'] + $s['reserved']) / $s['usable']) * 100)) : 0)
                        <div class="h-full rounded-full bg-sky-500 dark:bg-sky-400" style="width: {{ $pakai }}%"></div>
                    </div>

                    <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                        {{ $s['used'] }} terpakai · {{ $s['reserved'] }} dicadangkan
                    </p>

                    {{--
                        Peringatan ditempatkan DI DALAM kartu, bukan di halaman
                        terpisah: angka "bebas" di atas belum memperhitungkan
                        NIC ini, dan pembaca harus melihat keduanya sekaligus.
                    --}}
                    @if ($s['unknown_nics'] > 0)
                        <div class="mt-3 flex items-start gap-2 rounded-lg bg-amber-50 p-2.5 dark:bg-amber-900/30">
                            <x-lucide-triangle-alert class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                            <p class="text-xs text-amber-800 dark:text-amber-200">
                                <span class="font-medium">{{ $s['unknown_nics'] }} NIC</span>
                                di bridge ini belum diketahui alamatnya — angka bebas di atas
                                masih perkiraan.
                            </p>
                        </div>
                    @endif

                    @if ($this->expandedId === $subnet->id)
                        <div class="mt-3 border-t border-neutral-200 pt-3 dark:border-neutral-700">
                            <p class="mb-2 text-xs font-medium text-neutral-600 dark:text-neutral-300">
                                Alamat yang tampak bebas
                            </p>
                            @if (count($this->freeAddresses) === 0)
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                    Tidak ada alamat bebas.
                                </p>
                            @else
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($this->freeAddresses as $ip)
                                        <span class="rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-xs text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200">
                                            {{ $ip }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </x-nawasara-ui::page.card>
            @endforeach
        </div>
    @endif
</div>

<div>
    <x-nawasara-ui::page-header
        title="Riwayat Console"
        description="Siapa membuka console VM mana, kapan, dan berapa lama."
        :count="$this->stats['total']" />

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <x-nawasara-ui::stat-card compact icon="lucide-calendar-days" label="Hari Ini"
            :value="$this->stats['today']" />
        <x-nawasara-ui::stat-card compact icon="lucide-terminal" label="Masih Dibuka"
            :value="$this->stats['open']" />
        <x-nawasara-ui::stat-card compact icon="lucide-history" label="Total Tercatat"
            :value="$this->stats['total']" />
    </div>

    {{-- Peringatan yang membuat halaman ini punya alasan untuk ada. --}}
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800/50 dark:bg-amber-900/20">
        <p class="text-xs text-amber-800 dark:text-amber-300">
            Console memakai <strong>satu kredensial bersama</strong> (root@pam), sehingga di
            log Proxmox setiap sesi tampak sebagai root. Daftar inilah satu-satunya tempat
            siapa yang membukanya tercatat.
        </p>
    </div>

    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <x-nawasara-ui::filter-panel
                    label="Filter"
                    :state="['userFilter' => $userFilter, 'statusFilter' => $statusFilter, 'channelFilter' => $channelFilter]"
                    :multiple="['userFilter']"
                    :labels="['userFilter' => $this->userOptions, 'statusFilter' => $this->statusOptions, 'channelFilter' => $this->channelOptions]"
                    :dimensions="['userFilter' => 'Pengguna', 'statusFilter' => 'Status', 'channelFilter' => 'Jalur']">
                    <x-nawasara-ui::filter-group label="Pengguna" model="userFilter"
                        :items="$this->userOptions" icon="lucide-user" />
                    <x-nawasara-ui::filter-group label="Status" model="statusFilter"
                        :items="$this->statusOptions" icon="lucide-circle-check" />
                    <x-nawasara-ui::filter-group label="Jalur" model="channelFilter"
                        :items="$this->channelOptions" icon="lucide-route" />
                </x-nawasara-ui::filter-panel>
            </div>

            <x-nawasara-ui::search-input model="search" placeholder="Cari nama, NIP, atau VM..." />
        </div>

        <div wire:ignore data-filter-chips class="flex flex-wrap items-center gap-2"></div>

        @if ($search)
            <div class="flex flex-wrap items-center gap-2">
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            </div>
        @endif
    </div>

    @if ($sessions->isEmpty())
        @if ($this->hasFilter())
            <x-nawasara-ui::empty-state
                icon="lucide-search-x"
                title="Tidak ada yang cocok"
                description="Ubah kata kunci atau saringannya." />
        @else
            <x-nawasara-ui::empty-state
                icon="lucide-terminal"
                title="Belum ada akses console"
                description="Riwayat akan terisi begitu seseorang membuka console sebuah VM." />
        @endif
    @else
        <x-nawasara-ui::table :headers="['Pengguna', 'VM', 'Mulai', 'Selesai', 'Durasi']">
            <x-slot:table>
                @foreach ($sessions as $session)
                    <tr wire:key="session-{{ $session->id }}">
                        <td class="px-6 py-4 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $session->user_name }}
                            <div class="text-xs text-neutral-500 dark:text-neutral-400">
                                @if ($session->user_nip)
                                    NIP {{ $session->user_nip }} ·
                                @endif
                                {{ $session->user_email }}
                            </div>
                            {{-- Jalur dibedakan karena haknya berbeda: panel memakai
                                 root@pam lewat shell node, Akun Kisara memakai
                                 kisara-console@pve langsung ke container. --}}
                            <div class="mt-1">
                                @if ($session->channel === 'kisara')
                                    <x-nawasara-ui::badge color="info">Akun Kisara</x-nawasara-ui::badge>
                                @else
                                    <x-nawasara-ui::badge color="neutral">Panel</x-nawasara-ui::badge>
                                @endif
                                @if ($session->ip_address)
                                    <span class="ml-1 font-mono text-xs text-neutral-500 dark:text-neutral-400">{{ $session->ip_address }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $session->vm_name }}
                            <div class="font-mono text-xs text-neutral-500 dark:text-neutral-400">
                                {{ $session->node_name }} · {{ $session->vm_type === 'lxc' ? 'LXC' : 'QEMU' }} {{ $session->vmid }}
                            </div>
                            {{-- Status di bawah nama, bukan kolom sendiri: terlihat tanpa menggulir ke kanan. --}}
                            <div class="mt-1">
                                @if ($session->isStale())
                                    {{-- Dibedakan dari "masih dibuka": tabnya sudah lama tidak
                                         berdenyut, jadi menyebutnya aktif akan keliru. --}}
                                    <x-nawasara-ui::badge color="neutral">Terputus</x-nawasara-ui::badge>
                                @elseif ($session->isOpen())
                                    <x-nawasara-ui::badge color="success">Dibuka</x-nawasara-ui::badge>
                                @else
                                    <x-nawasara-ui::badge color="neutral">Selesai</x-nawasara-ui::badge>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ $session->started_at?->setTimezone(config('app.display_timezone', 'Asia/Jakarta'))->translatedFormat('d M Y H:i') }}
                        </td>
                        <td class="px-6 py-4 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ $session->ended_at?->setTimezone(config('app.display_timezone', 'Asia/Jakarta'))->translatedFormat('d M Y H:i') ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $session->duration_label }}
                            @if (\Nawasara\Proxmox\Support\ConsoleRecording::files($session->id))
                                <div class="mt-1">
                                    <a href="{{ route('nawasara-proxmox.console.recording', $session->id) }}" target="_blank"
                                       class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                                        <x-lucide-play class="size-3" /> Putar rekaman
                                    </a>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-slot:table>

            <x-slot:footer>
                {{ $sessions->links() }}
            </x-slot:footer>
        </x-nawasara-ui::table>
    @endif
</div>

<div>
    <x-nawasara-ui::page-header
        title="Login Otomatis Console"
        description="Mesin yang consolenya dijawab otomatis oleh Nawasara, tanpa pengguna mengetik kredensial."
        :count="$credentials->count()">
        @can('proxmox.console.credential')
            <x-nawasara-ui::button color="primary"
                x-on:click="$dispatch('console-credential-create')">
                <x-slot:icon><x-lucide-plus class="size-4" /></x-slot:icon>
                Tambah Mesin
            </x-nawasara-ui::button>
        @endcan
    </x-nawasara-ui::page-header>

    {{-- Penjelasan yang membuat halaman ini dapat dipakai dengan benar. --}}
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800/50 dark:bg-amber-900/20">
        <p class="text-xs text-amber-800 dark:text-amber-300">
            Mesin yang <strong>tidak terdaftar di sini</strong> consolenya tetap meminta login —
            itu keadaan bawaan. Menambahkan sebuah mesin berarti siapa pun yang berhak membuka
            console-nya di Nawasara langsung masuk tanpa kredensial mesin.
        </p>
        <p class="text-xs text-amber-800 dark:text-amber-300 mt-1.5">
            Untuk mesin yang bukan milik tim Anda, sebaiknya minta persetujuan pemiliknya lebih
            dulu. Mencabutnya cukup dengan menonaktifkan atau menghapus barisnya.
        </p>
    </div>

    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            <x-nawasara-ui::search-input model="search" placeholder="Cari nama mesin atau vmid..." />
        </div>
    </div>

    @if ($credentials->isEmpty())
        @if ($search !== '')
            <x-nawasara-ui::empty-state
                icon="lucide-search-x"
                title="Tidak ada yang cocok"
                description="Ubah kata kuncinya." />
        @else
            <x-nawasara-ui::empty-state
                icon="lucide-key-round"
                title="Belum ada mesin yang disetel"
                description="Semua console saat ini meminta login seperti biasa." />
        @endif
    @else
        <x-nawasara-ui::table :headers="['Mesin', 'Pengguna', 'Status', '']" stickyLast>
            <x-slot:table>
                @foreach ($credentials as $credential)
                    <tr wire:key="cred-{{ $credential->id }}">
                        <td class="px-6 py-4 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $credential->vm_name ?: '—' }}
                            <div class="font-mono text-xs text-neutral-500 dark:text-neutral-400">
                                {{ $credential->node_name }}/{{ $credential->vmid }}
                            </div>
                        </td>
                        <td class="px-6 py-4 font-mono text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $credential->login_user }}
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @if ($credential->is_active)
                                <x-nawasara-ui::badge color="success">Aktif</x-nawasara-ui::badge>
                            @else
                                <x-nawasara-ui::badge color="neutral">Nonaktif</x-nawasara-ui::badge>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                            <x-nawasara-ui::dropdown-menu-action :id="$credential->id" :items="[
                                [
                                    'type' => 'click',
                                    'label' => 'Ubah',
                                    'wire:click' => '$dispatch(\'console-credential-edit\', { credentialId: ' . $credential->id . ' })',
                                    'icon' => 'lucide-pencil',
                                    'permission' => 'proxmox.console.credential',
                                ],
                                [
                                    'type' => 'click',
                                    'label' => $credential->is_active ? 'Nonaktifkan' : 'Aktifkan',
                                    'wire:click' => 'toggleActive(' . $credential->id . ')',
                                    'icon' => $credential->is_active ? 'lucide-pause' : 'lucide-play',
                                    'permission' => 'proxmox.console.credential',
                                ],
                                [
                                    'type' => 'click',
                                    'label' => 'Hapus',
                                    'wire:click' => 'delete(' . $credential->id . ')',
                                    'icon' => 'lucide-trash-2',
                                    'confirm' => 'Hapus kredensial ' . ($credential->vm_name ?: $credential->vmid) . '? Console mesin ini akan kembali meminta login.',
                                    'permission' => 'proxmox.console.credential',
                                ],
                            ]" />
                        </td>
                    </tr>
                @endforeach
            </x-slot:table>
        </x-nawasara-ui::table>
    @endif
</div>

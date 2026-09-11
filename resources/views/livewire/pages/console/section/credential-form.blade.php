<div>
    <x-nawasara-ui::modal id="console-credential-form" maxWidth="lg"
        :title="$credentialId ? 'Ubah Login Otomatis' : 'Tambah Login Otomatis'">
        {{-- Tombol simpan berada di footer, yang dirender DI LUAR div konten
             modal — sehingga ia lolos dari <form> ini. Karena itu tombolnya
             mengikat balik lewat atribut form. --}}
        <form wire:submit="save" id="console-credential-form-el" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-nawasara-ui::form.input
                        label="Node"
                        wire:model="nodeName"
                        placeholder="pve-1" />
                </div>
                <div>
                    <x-nawasara-ui::form.input
                        label="VMID"
                        type="number"
                        wire:model="vmid"
                        placeholder="140" />
                </div>
            </div>

            <div>
                <x-nawasara-ui::form.input
                    label="Pengguna Login"
                    wire:model="loginUser"
                    placeholder="root" />
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                    Pengguna di dalam mesin, bukan pengguna Proxmox.
                </p>
            </div>

            <div>
                <x-nawasara-ui::form.input
                    label="Kata Sandi"
                    type="password"
                    wire:model="loginPassword" />
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                    @if ($credentialId)
                        Kosongkan bila tidak ingin mengubah sandi yang tersimpan.
                    @else
                        Disimpan terenkripsi, dan tidak pernah ditampilkan kembali.
                    @endif
                </p>
            </div>

            <div>
                <x-nawasara-ui::form.checkbox wire:model="isActive" label="Aktif" />
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                    Bila dimatikan, console mesin ini kembali meminta login seperti biasa.
                </p>
            </div>
        </form>

        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" variant="outline"
                @click="$dispatch('close-modal', 'console-credential-form')">Batal</x-nawasara-ui::button>
            <x-nawasara-ui::button type="submit" form="console-credential-form-el" color="primary">
                Simpan
            </x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>

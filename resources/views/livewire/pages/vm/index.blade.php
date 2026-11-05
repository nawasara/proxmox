<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Proxmox VE', 'url' => '#'], ['label' => 'Virtual Machines']]" />
    </x-slot>

    {{-- Title + description hoisted into the section component (which
         owns the lifecycle/snapshot/poll state). Index is a shell. --}}
    <x-nawasara-ui::page.container>
        @livewire('nawasara-proxmox.vm.section.table')

        {{-- Modal OPD & PIC milik registry: VM didaftarkan sebagai aset
             "Server / VM", jadi PIC-nya diatur di tempat yang sama dengan
             aset lain. Hanya dipasang bila registry terpasang dan pengguna
             berhak mengubah aset — komponennya menolak mount tanpa izin itu. --}}
        @if (\Nawasara\Proxmox\Services\VmRegistrySync::available())
            @can('registry.asset.manage')
                @livewire('nawasara-registry.asset.section.pics')
            @endcan
        @endif
    </x-nawasara-ui::page.container>
</div>

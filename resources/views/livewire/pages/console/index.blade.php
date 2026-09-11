<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Proxmox', 'url' => '#'], ['label' => 'Riwayat Console']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        @livewire('nawasara-proxmox.console.section.table')
    </x-nawasara-ui::page.container>
</div>

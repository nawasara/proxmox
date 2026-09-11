<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Proxmox', 'url' => '#'], ['label' => 'Login Otomatis Console']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        @livewire('nawasara-proxmox.console.section.credential-table')
        @livewire('nawasara-proxmox.console.section.credential-form')
    </x-nawasara-ui::page.container>
</div>

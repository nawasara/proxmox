<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Proxmox', 'url' => '#'], ['label' => 'Inventori IP']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <livewire:nawasara-proxmox.ip.section.subnets />

        <div class="mt-6">
            <livewire:nawasara-proxmox.ip.section.unknown-nics />
        </div>

        <div class="mt-6">
            <livewire:nawasara-proxmox.ip.section.table />
        </div>
    </x-nawasara-ui::page.container>
</div>

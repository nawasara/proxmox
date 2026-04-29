<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Proxmox VE', 'url' => '#'], ['label' => 'Virtual Machines']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Virtual Machines</x-nawasara-ui::page.title>
        <p class="text-sm text-gray-500 dark:text-neutral-400 mb-4">
            VM dan container (LXC) di seluruh node cluster Proxmox. Sync periodik tiap 15 menit.
        </p>

        @livewire('nawasara-proxmox.vm.section.table')
    </x-nawasara-ui::page.container>
</div>

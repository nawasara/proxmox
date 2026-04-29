<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Proxmox VE', 'url' => '#'], ['label' => 'Nodes']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Cluster Nodes</x-nawasara-ui::page.title>
        <p class="text-sm text-gray-500 dark:text-neutral-400 mb-4">
            Status dan resource per node Proxmox VE cluster.
        </p>

        @livewire('nawasara-proxmox.node.section.overview')
    </x-nawasara-ui::page.container>
</div>

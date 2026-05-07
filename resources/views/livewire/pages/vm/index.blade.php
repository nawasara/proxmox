<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Proxmox VE', 'url' => '#'], ['label' => 'Virtual Machines']]" />
    </x-slot>

    {{-- Title + description hoisted into the section component (which
         owns the lifecycle/snapshot/poll state). Index is a shell. --}}
    <x-nawasara-ui::page.container>
        @livewire('nawasara-proxmox.vm.section.table')
    </x-nawasara-ui::page.container>
</div>

<?php

namespace Nawasara\Proxmox\Livewire\Vm;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-proxmox::livewire.pages.vm.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}

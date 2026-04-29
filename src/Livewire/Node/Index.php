<?php

namespace Nawasara\Proxmox\Livewire\Node;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-proxmox::livewire.pages.node.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}

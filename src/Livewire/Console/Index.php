<?php

namespace Nawasara\Proxmox\Livewire\Console;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-proxmox::livewire.pages.console.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}

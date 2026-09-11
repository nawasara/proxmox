<?php

namespace Nawasara\Proxmox\Livewire\Console;

use Livewire\Component;

class Credential extends Component
{
    public function render()
    {
        return view('nawasara-proxmox::livewire.pages.console.credential')
            ->layout('nawasara-ui::components.layouts.app');
    }
}

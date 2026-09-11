<?php

namespace Nawasara\Proxmox\Livewire\Console\Section;

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Nawasara\AuthPrimitives\Attributes\RequiresSudo;
use Nawasara\AuthPrimitives\Traits\WithSudo;
use Nawasara\Proxmox\Models\ConsoleCredential;

/**
 * Daftar mesin yang disetel login otomatis.
 *
 * Mesin yang tidak ada di sini consolenya meminta login seperti biasa — itu
 * keadaan bawaan, dan menghapus barisnya adalah cara mencabutnya.
 */
class CredentialTable extends Component
{
    use WithSudo;

    #[Url(except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        // Daftar ini pendek; tidak dipaginasi.
    }

    #[On('console-credential-saved')]
    public function refreshList(): void
    {
        // Render ulang; data dibaca ulang di render().
    }

    #[RequiresSudo(reason: 'mengaktifkan/menonaktifkan kredensial console')]
    public function toggleActive(int $credentialId): void
    {
        $this->authorize('proxmox.console.credential');

        $credential = ConsoleCredential::findOrFail($credentialId);
        $credential->update(['is_active' => ! $credential->is_active]);

        $this->dispatch(
            'toast',
            type: 'success',
            message: $credential->is_active
                ? "Login otomatis untuk {$credential->vm_name} diaktifkan."
                : "Login otomatis untuk {$credential->vm_name} dimatikan — console kembali meminta login.",
        );
    }

    #[RequiresSudo(reason: 'menghapus kredensial console')]
    public function delete(int $credentialId): void
    {
        $this->authorize('proxmox.console.credential');

        $credential = ConsoleCredential::findOrFail($credentialId);
        $name = $credential->vm_name ?: $credential->node_name.'/'.$credential->vmid;
        $credential->delete();

        $this->dispatch('toast', type: 'success', message: "Kredensial {$name} dihapus — console kembali meminta login.");
    }

    public function render()
    {
        $credentials = ConsoleCredential::query()
            ->when($this->search, fn ($q) => $q->where('vm_name', 'like', "%{$this->search}%")
                ->orWhere('node_name', 'like', "%{$this->search}%")
                ->orWhere('vmid', 'like', "%{$this->search}%"))
            ->orderBy('vm_name')
            ->get();

        return view('nawasara-proxmox::livewire.pages.console.section.credential-table', [
            'credentials' => $credentials,
        ]);
    }
}

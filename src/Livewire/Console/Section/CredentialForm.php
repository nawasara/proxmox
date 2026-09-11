<?php

namespace Nawasara\Proxmox\Livewire\Console\Section;

use Livewire\Attributes\On;
use Livewire\Component;
use Nawasara\AuthPrimitives\Attributes\RequiresSudo;
use Nawasara\AuthPrimitives\Traits\WithSudo;
use Nawasara\Proxmox\Models\ConsoleCredential;
use Nawasara\Proxmox\Models\ProxmoxVm;

/**
 * Menyetel kredensial login otomatis untuk satu mesin.
 *
 * Digerbang sudo seluruhnya: yang disimpan di sini adalah kata sandi yang
 * membuka shell pada mesin produksi, dan yang membacanya kembali sama saja
 * memegang mesin itu.
 */
class CredentialForm extends Component
{
    use WithSudo;

    public ?int $credentialId = null;

    public string $nodeName = '';

    public ?int $vmid = null;

    public string $vmName = '';

    public string $loginUser = 'root';

    public string $loginPassword = '';

    public bool $isActive = true;

    protected function rules(): array
    {
        return [
            'nodeName' => ['required', 'string', 'max:100'],
            'vmid' => ['required', 'integer', 'min:1'],
            'loginUser' => ['required', 'string', 'max:100'],
            'loginPassword' => ['required', 'string', 'max:255'],
            'isActive' => ['boolean'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function getVmOptionsProperty(): array
    {
        return ProxmoxVm::query()
            ->where('template', false)
            ->orderBy('name')
            ->get(['node_name', 'vmid', 'name'])
            ->map(fn ($vm) => [
                'key' => $vm->node_name.':'.$vm->vmid,
                'label' => $vm->name.' — '.$vm->node_name.'/'.$vm->vmid,
            ])
            ->all();
    }

    #[On('console-credential-create')]
    #[RequiresSudo(reason: 'menyetel kredensial login console')]
    public function create(): void
    {
        $this->resetForm();
        $this->dispatch('modal-open:console-credential-form');
    }

    #[On('console-credential-edit')]
    #[RequiresSudo(reason: 'mengubah kredensial login console')]
    public function edit(int $credentialId): void
    {
        $credential = ConsoleCredential::findOrFail($credentialId);

        $this->credentialId = $credential->id;
        $this->nodeName = $credential->node_name;
        $this->vmid = $credential->vmid;
        $this->vmName = (string) $credential->vm_name;
        $this->loginUser = $credential->login_user;
        $this->isActive = $credential->is_active;

        // ⚠️ Sandi TIDAK diisikan kembali ke formulir.
        //
        // Mengisinya berarti menaruh sandi mesin ke dalam snapshot Livewire di
        // peramban setiap kali seseorang membuka formulir ini untuk mengubah
        // hal lain — misalnya sekadar menonaktifkannya. Dibiarkan kosong,
        // dan hanya ditulis bila memang diisi ulang.
        $this->loginPassword = '';

        $this->resetErrorBag();
        $this->dispatch('modal-open:console-credential-form');
    }

    #[RequiresSudo(reason: 'menyimpan kredensial login console')]
    public function save(): void
    {
        $this->authorize('proxmox.console.credential');

        // Saat mengubah, sandi boleh dikosongkan untuk mempertahankan yang ada.
        $rules = $this->rules();
        if ($this->credentialId && $this->loginPassword === '') {
            unset($rules['loginPassword']);
        }

        $data = $this->validate($rules);

        $vm = ProxmoxVm::where('node_name', $data['nodeName'])
            ->where('vmid', $data['vmid'])
            ->first();

        $values = [
            'vm_name' => $vm?->name ?? $this->vmName,
            'login_user' => $data['loginUser'],
            'is_active' => $data['isActive'],
        ];

        if (! empty($data['loginPassword'] ?? '')) {
            $values['login_password'] = $data['loginPassword'];
        }

        ConsoleCredential::updateOrCreate(
            ['node_name' => $data['nodeName'], 'vmid' => $data['vmid']],
            $values,
        );

        $this->dispatch('console-credential-saved');
        $this->dispatch('modal-close:console-credential-form');
        $this->dispatch('toast', type: 'success', message: 'Kredensial console disimpan.');

        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->credentialId = null;
        $this->nodeName = '';
        $this->vmid = null;
        $this->vmName = '';
        $this->loginUser = 'root';
        $this->loginPassword = '';
        $this->isActive = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('nawasara-proxmox::livewire.pages.console.section.credential-form');
    }
}

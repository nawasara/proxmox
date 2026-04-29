<?php

namespace Nawasara\Proxmox\Livewire\Vm\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Proxmox\Models\ProxmoxNode;
use Nawasara\Proxmox\Models\ProxmoxVm;
use Nawasara\Proxmox\Repositories\ProxmoxVmRepository;
use Nawasara\Ui\Livewire\Concerns\HasBrowserToast;

class Table extends Component
{
    use HasBrowserToast;
    use WithPagination;

    #[Url(except: '')]
    public string $nodeFilter = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: '')]
    public string $typeFilter = '';

    public string $templateFilter = 'hide'; // default sembunyikan template

    public string $search = '';
    public int $perPage = 50;

    // Detail modal
    public ?int $detailId = null;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedNodeFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedTypeFilter(): void { $this->resetPage(); }
    public function updatedTemplateFilter(): void { $this->resetPage(); }

    protected function repo(): ProxmoxVmRepository
    {
        return new ProxmoxVmRepository();
    }

    #[Computed]
    public function vms()
    {
        return $this->repo()->list([
            'search' => $this->search ?: null,
            'node' => $this->nodeFilter ?: null,
            'status' => $this->statusFilter ?: null,
            'type' => $this->typeFilter ?: null,
            'template' => $this->templateFilter ?: null,
        ], $this->perPage);
    }

    #[Computed]
    public function nodeOptions(): array
    {
        $nodes = ProxmoxNode::orderBy('node_name')->pluck('node_name')->all();
        $opts = ['all' => 'Semua Node'];
        foreach ($nodes as $n) {
            $opts[$n] = $n;
        }
        return $opts;
    }

    #[Computed]
    public function statusCounts(): array
    {
        return ProxmoxVm::query()
            ->where('template', false)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();
    }

    #[Computed]
    public function lastSyncedAt(): ?string
    {
        $when = $this->repo()->lastSyncedAt();
        return $when ? $when->diffForHumans() : null;
    }

    public function refresh(): void
    {
        Gate::authorize('proxmox.sync.execute');
        $this->repo()->syncNow();
        $this->toastSuccess('Sync dispatched. Data akan refresh dalam beberapa detik.');
    }

    public function openDetail(int $id): void
    {
        $this->detailId = $id;
        $this->dispatch('modal-open:proxmox-vm-detail');
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
        $this->dispatch('modal-close:proxmox-vm-detail');
    }

    #[Computed]
    public function detail(): ?ProxmoxVm
    {
        return $this->detailId ? ProxmoxVm::find($this->detailId) : null;
    }

    public function render()
    {
        return view('nawasara-proxmox::livewire.pages.vm.section.table');
    }
}

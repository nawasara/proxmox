<?php

namespace Nawasara\Proxmox\Livewire\Console\Section;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Proxmox\Models\ConsoleSession;

/**
 * Riwayat akses console VM.
 *
 * Console memakai satu kredensial bersama (`root@pam` dari Vault), sehingga di
 * sisi Proxmox setiap sesi tampak sebagai root. Halaman inilah satu-satunya
 * tempat pertanyaan "siapa masuk ke mesin ini, kapan, dan berapa lama" dapat
 * dijawab.
 */
class Table extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /** @var array<int, string> */
    #[Url]
    public array $userFilter = [];

    #[Url(except: '')]
    public string $statusFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedUserFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function hasFilter(): bool
    {
        return $this->search !== '' || $this->userFilter !== [] || $this->statusFilter !== '';
    }

    /** @return array<string, string> */
    #[Computed]
    public function userOptions(): array
    {
        return ConsoleSession::query()
            ->select('user_name')
            ->distinct()
            ->orderBy('user_name')
            ->pluck('user_name', 'user_name')
            ->all();
    }

    /** @return array<string, string> */
    #[Computed]
    public function statusOptions(): array
    {
        return [
            'open' => 'Masih dibuka',
            'closed' => 'Selesai',
        ];
    }

    /** Ringkasan untuk stat card. */
    #[Computed]
    public function stats(): array
    {
        $today = ConsoleSession::whereDate('started_at', today());

        return [
            'today' => (clone $today)->count(),
            'open' => ConsoleSession::whereNull('ended_at')->count(),
            'total' => ConsoleSession::count(),
        ];
    }

    public function render()
    {
        $sessions = ConsoleSession::query()
            ->when($this->search, function ($query) {
                $query->where(function ($sub) {
                    $sub->where('user_name', 'like', "%{$this->search}%")
                        ->orWhere('user_email', 'like', "%{$this->search}%")
                        ->orWhere('user_nip', 'like', "%{$this->search}%")
                        ->orWhere('vm_name', 'like', "%{$this->search}%")
                        ->orWhere('vmid', 'like', "%{$this->search}%");
                });
            })
            ->when($this->userFilter, fn ($q) => $q->whereIn('user_name', $this->userFilter))
            ->when($this->statusFilter === 'open', fn ($q) => $q->whereNull('ended_at'))
            ->when($this->statusFilter === 'closed', fn ($q) => $q->whereNotNull('ended_at'))
            ->orderByDesc('started_at')
            ->paginate(25);

        return view('nawasara-proxmox::livewire.pages.console.section.table', [
            'sessions' => $sessions,
        ]);
    }
}

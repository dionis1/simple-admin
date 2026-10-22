<?php

namespace App\Livewire\Governance;

use App\Governance\AuditHistory;
use App\Livewire\Concerns\ResolvesUser;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class History extends Component
{
    use ResolvesUser, WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.governance.history', ['events' => app(AuditHistory::class)->query($this->actor(), $this->search)->paginate(25)])->title(__('Audit history'));
    }
}

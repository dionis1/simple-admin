<?php

namespace App\Livewire\Organizations;

use App\Actions\Organizations\UpdateOrganization;
use App\Livewire\Concerns\ResolvesUser;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    use ResolvesUser;

    public string $name = '';

    public function mount(): void
    {
        $organization = app(TenantContext::class)->resolve($this->actor())->organization()->firstOrFail();
        Gate::authorize('view', $organization);
        $this->name = $organization->name;
    }

    public function save(UpdateOrganization $update): void
    {
        $update($this->actor(), $this->name);
        session()->flash('message', 'Organization updated.');
    }

    public function render(): View
    {
        $organization = app(TenantContext::class)->resolve($this->actor())->organization()->firstOrFail();
        Gate::authorize('view', $organization);

        return view('livewire.organizations.show', compact('organization'))->title('Organization');
    }
}

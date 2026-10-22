<?php

namespace App\Livewire\Roles;

use App\Livewire\Concerns\ResolvesUser;
use App\Models\Role;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use ResolvesUser;

    public function render(): View
    {
        Gate::authorize('viewAny', Role::class);
        $organization = app(TenantContext::class)->resolve($this->actor())->organization()->firstOrFail();

        return view('livewire.roles.index', ['roles' => $organization->roles()->with('permissions')->orderBy('id')->get()])->title('Roles');
    }
}

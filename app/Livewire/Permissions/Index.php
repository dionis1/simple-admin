<?php

namespace App\Livewire\Permissions;

use App\Livewire\Concerns\ResolvesUser;
use App\Models\Permission;
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
        Gate::authorize('viewAny', Permission::class);
        $id = app(TenantContext::class)->resolve($this->actor())->organization_id;

        return view('livewire.permissions.index', [
            'permissions' => Permission::query()->with(['roles' => fn ($query) => $query->where('organization_id', $id)])
                ->orderBy('resource')->orderBy('action')->get(),
        ])->title('Permissions');
    }
}

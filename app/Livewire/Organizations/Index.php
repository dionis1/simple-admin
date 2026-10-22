<?php

namespace App\Livewire\Organizations;

use App\Actions\Organizations\CreateOrganization;
use App\Actions\Organizations\SwitchOrganization;
use App\Livewire\Concerns\ResolvesUser;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    use ResolvesUser;

    public string $name = '';

    public function create(CreateOrganization $create, SwitchOrganization $switch): void
    {
        $organization = $create($this->actor(), $this->name);
        $switch($this->actor(), $organization->id);
        $this->redirectRoute('dashboard', navigate: true);
    }

    public function choose(int $organizationId, SwitchOrganization $switch): void
    {
        $switch($this->actor(), $organizationId);
        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.organizations.index', [
            'memberships' => $this->actor()->memberships()->with('organization')->orderBy('id')->get(),
        ])->title('Organizations');
    }
}

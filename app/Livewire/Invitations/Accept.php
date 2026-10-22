<?php

namespace App\Livewire\Invitations;

use App\Actions\Invitations\AcceptInvitation;
use App\Actions\Organizations\SwitchOrganization;
use App\Livewire\Concerns\ResolvesUser;
use App\Models\Organization;
use App\Models\Role;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class Accept extends Component
{
    use ResolvesUser;

    #[Locked]
    public string $token = '';

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    public function accept(AcceptInvitation $accept, SwitchOrganization $switch): void
    {
        $membership = $accept($this->actor(), $this->token);
        $switch($this->actor(), $membership->organization_id);
        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render(): View
    {
        $invitation = app(AcceptInvitation::class)->preview($this->actor(), $this->token);

        return view('livewire.invitations.accept', ['organization' => Organization::findOrFail($invitation->organization_id), 'role' => Role::findOrFail($invitation->role_id)])->title(__('Review invitation'));
    }
}

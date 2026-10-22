<?php

namespace App\Livewire\Security;

use App\Actions\Security\AccountLifecycle;
use App\Actions\Security\ConfirmPassword;
use App\Actions\Security\IssueApiToken;
use App\Actions\Security\RevokeApiToken;
use App\Actions\Security\RevokeSession;
use App\Enums\AccessStatus;
use App\Enums\PermissionKey;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Identity extends Component
{
    public string $currentPassword = '';

    public string $code = '';

    public string $tokenName = '';

    public int $tokenDays = 30;

    /** @var array<int,string> */
    public array $abilities = [];

    private function actor(): User
    {
        $authenticated = Auth::user();
        $user = $authenticated instanceof User ? User::find($authenticated->id) : null;
        abort_unless($user instanceof User && $user->status !== AccessStatus::Suspended, 403);

        return $user;
    }

    public function mfa(string $operation): void
    {
        $this->resetValidation();
        try {
            DB::transaction(function () use ($operation) {
                $user = User::query()->lockForUpdate()->findOrFail($this->actor()->id);
                $user = app(ConfirmPassword::class)($user, $this->currentPassword);
                abort_unless($user->isActive(), 403);
                abort_if($operation === 'regenerate' && ! $user->two_factor_confirmed_at, 403);
                match ($operation) {
                    'enable' => app(EnableTwoFactorAuthentication::class)($user),
                    'confirm' => app(ConfirmTwoFactorAuthentication::class)($user, $this->code),
                    'disable' => app(DisableTwoFactorAuthentication::class)($user),
                    'regenerate' => app(GenerateNewRecoveryCodes::class)($user),
                    default => abort(422),
                };
                if (in_array($operation, ['confirm', 'regenerate'], true)) {
                    session()->flash('recovery_codes', $user->fresh()?->recoveryCodes());
                }
            });
            session()->flash('message', __('Security settings updated.'));
        } finally {
            $this->reset('currentPassword', 'code');
        }
    }

    public function createToken(IssueApiToken $issue): void
    {
        $this->resetValidation();
        $token = $issue($this->actor(), $this->currentPassword, $this->tokenName, $this->abilities, $this->tokenDays);
        session()->flash('new_api_token', $token);
        $this->reset('currentPassword', 'tokenName', 'abilities');
    }

    public function revokeToken(int $id, RevokeApiToken $revoke): void
    {
        $this->resetValidation();
        $revoke($this->actor(), $this->currentPassword, $id);
        $this->reset('currentPassword');
        session()->flash('message', __('API token revoked.'));
    }

    public function revokeSession(string $id, RevokeSession $revoke): void
    {
        $this->resetValidation();
        $rows = DB::table('sessions')->where('user_id', $this->actor()->id)->pluck('id');
        $actualId = $rows->first(fn ($value) => hash_equals(hash_hmac('sha256', $value, (string) config('app.key')), $id));
        abort_unless(is_string($actualId), 404);
        $id = $actualId;
        $current = session()->getId() === $id;
        $revoke($this->actor(), $this->currentPassword, $id);
        $this->reset('currentPassword');
        if ($current) {
            Auth::guard('web')->logout();
            session()->invalidate();
            $this->redirectRoute('login');
        } else {
            session()->flash('message', __('Session revoked.'));
        }
    }

    public function lifecycle(bool $reactivate, AccountLifecycle $change): void
    {
        $this->resetValidation();
        $change($this->actor(), $this->currentPassword, $reactivate);
        $this->reset('currentPassword');
        Auth::guard('web')->setUser($this->actor()->fresh() ?? $this->actor());
        session()->flash('message', __('Account status updated.'));
    }

    public function render(): View
    {
        $user = $this->actor()->fresh();
        abort_unless($user !== null, 403);
        $confirmed = (int) session('auth.password_confirmed_at', 0) > time() - (int) config('auth.password_timeout', 10800);
        $context = app(TenantContext::class);

        return view('livewire.security.identity', [
            'newToken' => session()->pull('new_api_token'), 'recoveryCodes' => session()->pull('recovery_codes'),
            'account' => $user, 'setupQr' => $confirmed && $user->two_factor_secret && ! $user->two_factor_confirmed_at ? $user->twoFactorQrCodeSvg() : null,
            'sessions' => DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->get(['id', 'ip_address', 'user_agent', 'last_activity'])->map(fn ($row) => (object) ['reference' => hash_hmac('sha256', $row->id, (string) config('app.key')), 'current' => $row->id === session()->getId(), 'ip_address' => $row->ip_address, 'user_agent' => $row->user_agent, 'last_activity' => $row->last_activity]),
            'tokens' => PersonalAccessToken::where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->id)->with('organization')->orderByDesc('id')->get(),
            'availableAbilities' => array_filter(PermissionKey::cases(), fn ($key) => $context->allows($user, $key)),
        ])->title(__('Account security'));
    }
}

<?php

namespace App\Providers;

use App\Actions\Governance\SupportAccess;
use App\Actions\Security\RevokeCredentials;
use App\Enums\AccessStatus;
use App\Enums\PermissionKey;
use App\Governance\Audit;
use App\Governance\QueueVisibility;
use App\Http\Middleware\AccountAccess;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SupportSession;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Notifications\SecurityNotice;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        app(QueueVisibility::class)->register();
        Gate::before(function (User $user, string $ability): ?bool {
            if (session()->has(SupportAccess::SESSION_KEY) && ! in_array($ability, ['tenant.permission', 'view', 'viewAny'], true)) {
                return false;
            }

            return null;
        });
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by((string) $request->user()?->getAuthIdentifier()));
        Event::listen(Failed::class, function ($event): void {
            app(Audit::class)->record('security.login_failed', null, 'Authentication', null);
        });
        Event::listen(Login::class, function ($event): void {
            if ($event->user instanceof User) {
                app(Audit::class)->record('security.login', null, 'User', $event->user->id, [], $event->user);
                $event->user->notify(new SecurityNotice('A new sign-in was detected.'));
            }
        });
        foreach ([TwoFactorAuthenticationConfirmed::class, TwoFactorAuthenticationDisabled::class, RecoveryCodesGenerated::class] as $eventClass) {
            Event::listen($eventClass, function ($event): void {
                if ($event->user instanceof User) {
                    app(RevokeCredentials::class)($event->user);
                    $event->user->notify(new SecurityNotice('Your multi-factor authentication settings changed.'));
                }
            });
        }
        User::updated(function (User $user): void {
            $emailChanged = $user->wasChanged('email');
            $oldEmail = $user->getRawOriginal('email');
            if ($user->wasChanged('password') || $user->wasChanged('email') || ($user->wasChanged('status') && $user->status !== AccessStatus::Active)) {
                app(RevokeCredentials::class)($user);
                $user->notify(new SecurityNotice('Your account credentials or access status changed.'));
                if ($emailChanged) {
                    Notification::route('mail', $oldEmail)->notify((new SecurityNotice('Your account email address changed.'))->locale($user->locale));
                }
            }
        });
        Gate::define('tenant.permission', fn (User $user, PermissionKey|string $permission): bool => app(TenantContext::class)->allows($user, $permission));
        Livewire::addPersistentMiddleware([EnsureTenantContext::class, AccountAccess::class, SetLocale::class, RequirePassword::class, SupportSession::class]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);
        Model::preventLazyLoading(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

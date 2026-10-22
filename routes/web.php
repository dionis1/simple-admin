<?php

use App\Actions\Governance\SupportAccess;
use App\Enums\PermissionKey;
use App\Governance\Health;
use App\Http\Controllers\AuditExportController;
use App\Http\Middleware\EnsureTenantContext;
use App\Livewire\Governance\Approvals;
use App\Livewire\Governance\Dashboard;
use App\Livewire\Governance\History;
use App\Livewire\Invitations\Accept;
use App\Livewire\Organizations\Index;
use App\Livewire\Organizations\Show;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::post('locale', function (Request $request) {
    $data = $request->validate(['locale' => ['required', Rule::in(['en', 'de'])]]);
    $request->session()->put('locale', $data['locale']);
    if ($request->user() instanceof User) {
        $request->user()->forceFill(['locale' => $data['locale']])->save();
    }
    $previous = url()->previous();

    return redirect(parse_url($previous, PHP_URL_HOST) === parse_url(config('app.url'), PHP_URL_HOST) ? $previous : route('home'));
})->name('locale.update');

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('invitations/accept/{token}', Accept::class)->middleware('throttle:30,1')->name('invitations.accept');
    Route::get('organizations', Index::class)->name('organizations.index');

    Route::middleware(EnsureTenantContext::class)->group(function () {
        Route::get('dashboard', Dashboard::class)->middleware('can:tenant.permission,"'.PermissionKey::ViewDashboard->value.'"')->name('dashboard');
        Route::get('audit', History::class)->name('audit.index');
        Route::get('audit/export', AuditExportController::class)->middleware('throttle:6,1')->name('audit.export');
        Route::get('approvals', Approvals::class)->name('approvals.index');
        Route::get('organization', Show::class)->name('organization.show');
        Route::get('invitations', App\Livewire\Invitations\Index::class)->name('invitations.index');
        Route::get('structure', App\Livewire\Structure\Index::class)->name('structure.index');
        Route::get('memberships', App\Livewire\Memberships\Index::class)->name('memberships.index');
        Route::get('roles', App\Livewire\Roles\Index::class)->name('roles.index');
        Route::get('permissions', App\Livewire\Permissions\Index::class)->name('permissions.index');
    });
});

Route::post('support/stop', function (Request $request) {
    $actor = $request->user();
    abort_unless($actor instanceof User, 403);
    app(SupportAccess::class)->stop($actor);

    return redirect()->route('dashboard');
})->middleware('auth')->name('support.stop');

Route::get('ready', function () {
    $checks = app(Health::class)->check();
    $ready = $checks['database'] && $checks['cache'] && $checks['worker'];

    return response()->json(['status' => $ready ? 'ok' : 'unavailable'], $ready ? 200 : 503)->header('Cache-Control', 'no-store');
})->middleware('throttle:60,1')->name('health.ready');

require __DIR__.'/settings.php';

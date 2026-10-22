<?php

use App\Enums\PermissionKey;
use App\Http\Middleware\EnsureTenantContext;
use App\Livewire\Organizations\Index;
use App\Livewire\Organizations\Show;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('organizations', Index::class)->name('organizations.index');

    Route::middleware(EnsureTenantContext::class)->group(function () {
        Route::view('dashboard', 'dashboard')->middleware('can:tenant.permission,"'.PermissionKey::ViewDashboard->value.'"')->name('dashboard');
        Route::get('organization', Show::class)->name('organization.show');
        Route::get('memberships', App\Livewire\Memberships\Index::class)->name('memberships.index');
        Route::get('roles', App\Livewire\Roles\Index::class)->name('roles.index');
        Route::get('permissions', App\Livewire\Permissions\Index::class)->name('permissions.index');
    });
});

require __DIR__.'/settings.php';

<?php

use App\Http\Controllers\Api\MembershipController;
use App\Http\Middleware\ApiTenant;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', ApiTenant::class, SetLocale::class, 'throttle:api'])->group(function () {
    Route::get('memberships', [MembershipController::class, 'index']);
    Route::get('memberships/{membership}', [MembershipController::class, 'show'])->whereNumber('membership');
    Route::patch('memberships/{membership}', [MembershipController::class, 'update'])->whereNumber('membership');
});

<?php

use App\Actions\Memberships\RemoveMembership;
use App\Actions\Memberships\UpdateMembership;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Isolated PostgreSQL worker for the final-owner concurrency regression.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('testing') || config('database.connections.pgsql.database') !== 'simple_admin_testing') {
    throw new RuntimeException('Owner race workers require the dedicated test database.');
}

[$script, $userId, $organizationId, $membershipId, $roleId, $operation, $marker, $tag] = $argv;
$user = User::findOrFail($userId);
DB::select("SELECT set_config('application_name', ?, false)", [$tag]);
session()->put(TenantContext::SESSION_KEY, (int) $organizationId);

try {
    // Signal that both workers have resolved identity before racing for the organization lock.
    file_put_contents($marker, 'ready');
    $deadline = microtime(true) + 20;
    while (! file_exists($marker.'.go')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Race start signal timed out.');
        }
        usleep(10000);
    }
    if ($operation === 'remove') {
        app(RemoveMembership::class)($user, (int) $membershipId);
    } else {
        app(UpdateMembership::class)($user, (int) $membershipId, (int) $roleId, 'suspended');
    }
    echo 'changed';
} catch (ValidationException $e) {
    echo 'blocked';
}

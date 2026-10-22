<?php

use App\Actions\Governance\DecideApproval;
use App\Actions\Governance\SupportAccess;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.connections.pgsql.database') !== 'simple_admin_testing') {
    throw new RuntimeException('Dedicated test database required.');
}
[$script, $mode, $actorId, $orgId, $id, $code, $marker, $tag] = $argv;
$request = Request::create('/approvals', 'POST');
$app->instance('request', $request);
$session = app('session')->driver();
$session->start();
$session->put(TenantContext::SESSION_KEY, (int) $orgId);
$request->setLaravelSession($session);
$user = User::findOrFail((int) $actorId);
Auth::setUser($user);
Notification::fake();
DB::select("SELECT set_config('application_name', ?, false)", [$tag]);
file_put_contents($marker, 'ready');
$deadline = microtime(true) + 20;
while (! file_exists($marker.'.go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Start timed out.');
    }
    usleep(10000);
}
try {
    if ($mode === 'decision') {
        app(DecideApproval::class)($user, (int) $id, true, 'password');
    } else {
        app(SupportAccess::class)->start($user, (int) $id, 'password', $code);
    }
    echo '204';
} catch (HttpException $exception) {
    echo $exception->getStatusCode();
}

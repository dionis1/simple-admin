<?php

use App\Actions\Invitations\AcceptInvitation;
use App\Http\Middleware\SerializeMfa;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.connections.pgsql.database') !== 'simple_admin_testing') {
    throw new RuntimeException('Dedicated test database required.');
}
[$script,$mode,$userId,$secret,$marker,$tag] = $argv;
$user = User::findOrFail((int) $userId);
DB::select("SELECT set_config('application_name', ?, false)", [$tag]);
file_put_contents($marker, 'ready');
$deadline = microtime(true) + 20;
while (! file_exists($marker.'.go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Start timed out.');
    } usleep(10000);
}
if ($mode === 'invitation') {
    try {
        app(AcceptInvitation::class)($user, $secret);
        echo '204';
    } catch (HttpException $exception) {
        echo $exception->getStatusCode();
    }
} else {
    $request = Request::create('/two-factor-challenge', 'POST', ['recovery_code' => $secret], [], [], ['HTTP_ACCEPT' => 'application/json']);
    $app->instance('request', $request);
    $session = app('session')->driver();
    $session->start();
    $session->put('login.id', $user->id);
    $request->setLaravelSession($session);
    try {
        $response = app(SerializeMfa::class)->handle($request, function (Request $request) use ($app) {
            $form = TwoFactorLoginRequest::createFrom($request);
            $form->setContainer($app)->setRedirector(app('redirect'));
            $result = app(TwoFactorAuthenticatedSessionController::class)->store($form);

            return $result instanceof Responsable ? $result->toResponse($form) : $result;
        });
        echo $response->getStatusCode();
    } catch (ValidationException $exception) {
        // Match the HTTP exception renderer used by the real Fortify endpoint.
        echo $exception->status;
    }
}

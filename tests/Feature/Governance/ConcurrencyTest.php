<?php

use App\Actions\Governance\DecideApproval;
use App\Actions\Governance\RequestApproval;
use App\Enums\RoleName;
use App\Models\AuditEvent;
use App\Models\SupportGrant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\Process\Process;

uses(DatabaseMigrations::class);

it('executes a decision or consumes a support grant only once under concurrent requests', function (string $mode) {
    Notification::fake();
    [$owner, $org] = tenantWorkspace();
    $reviewer = tenantMember($owner, $org, RoleName::Admin)->user()->firstOrFail();
    $target = tenantMember($owner, $org, RoleName::Viewer);
    tenantActor($owner, $org);
    $request = app(RequestApproval::class)($owner, $mode === 'decision' ? 'revoke_org_tokens' : 'support_access', 'Investigate a reported security incident.', $target->id);
    $code = '';
    $id = $request->id;
    $actor = $reviewer;
    $lockTable = 'organizations';
    $lockId = $org->id;
    if ($mode === 'support') {
        tenantActor($reviewer, $org);
        app(DecideApproval::class)($reviewer, $request->id, true, 'password');
        $grant = SupportGrant::where('approval_request_id', $request->id)->firstOrFail();
        $secret = (new Google2FA)->generateSecretKey();
        $owner->forceFill(['two_factor_secret' => encrypt($secret), 'two_factor_confirmed_at' => now()])->save();
        $code = (new Google2FA)->getCurrentOtp($secret);
        $actor = $owner;
        $id = $grant->id;
        $lockTable = 'users';
        $lockId = $owner->id;
    }
    $workers = $markers = $tags = [];
    DB::beginTransaction();
    DB::table($lockTable)->where('id', $lockId)->lockForUpdate()->first();
    try {
        for ($i = 0; $i < 2; $i++) {
            $marker = sys_get_temp_dir().'/sa-governance-'.bin2hex(random_bytes(8));
            $tag = 'sa-governance-'.bin2hex(random_bytes(8));
            $markers[] = $marker;
            $tags[] = $tag;
            $worker = new Process([PHP_BINARY, base_path('tests/Support/governance-race.php'), $mode, (string) $actor->id, (string) $org->id, (string) $id, $code, $marker, $tag], base_path(), [
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'simple_admin_testing', 'DB_URL' => '', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
            ]);
            $worker->setTimeout(30)->start();
            $workers[] = $worker;
        }
        $deadline = microtime(true) + 15;
        while (! file_exists($markers[0]) || ! file_exists($markers[1])) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Workers did not start.');
            }
            usleep(10000);
        }
        foreach ($markers as $marker) {
            touch($marker.'.go');
        }
        do {
            DB::select('SELECT pg_stat_clear_snapshot()');
            $waiting = DB::selectOne("SELECT count(*) AS waiting FROM pg_stat_activity WHERE application_name IN (?, ?) AND wait_event_type = 'Lock'", $tags)->waiting;
            if ((int) $waiting === 2) {
                break;
            }
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Both governance requests must wait for the lock.');
            }
            usleep(10000);
        } while (true);
        DB::commit();
        $results = [];
        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput());
            $results[] = trim($worker->getOutput());
        }
        sort($results);
        expect($results)->toBe($mode === 'decision' ? ['204', '409'] : ['204', '403']);
        expect(AuditEvent::where('event', $mode === 'decision' ? 'tokens.organization_revoked' : 'support.started')->count())->toBe(1);
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($workers as $worker) {
            $worker->stop();
        }
        foreach ($markers as $marker) {
            @unlink($marker);
            @unlink($marker.'.go');
        }
    }
})->with(['decision', 'support']);

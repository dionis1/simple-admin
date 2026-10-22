<?php

use App\Actions\Invitations\IssueInvitation;
use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\OrganizationInvitation;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\Process\Process;

uses(DatabaseMigrations::class);

it('consumes an invitation or MFA recovery code only once under concurrent requests', function (string $mode) {
    Notification::fake();
    $recipient = User::factory()->create();
    if ($mode === 'invitation') {
        [$owner,$org] = tenantWorkspace();
        tenantActor($owner, $org);
        $invitation = app(IssueInvitation::class)($owner, $recipient->email, tenantRole($org, RoleName::Viewer)->id);
        $secret = '';
        Notification::assertSentOnDemand(OrganizationInvitation::class, function ($notice) use (&$secret) {
            $secret = $notice->token;

            return true;
        });
        $lockTable = 'organizations';
        $lockId = $org->id;
    } else {
        $secret = 'single-use-recovery';
        $recipient->forceFill(['two_factor_secret' => encrypt((new Google2FA)->generateSecretKey()), 'two_factor_recovery_codes' => encrypt(json_encode([$secret])), 'two_factor_confirmed_at' => now()])->save();
        $lockTable = 'users';
        $lockId = $recipient->id;
    }
    $workers = [];
    $markers = [];
    $tags = [];
    DB::beginTransaction();
    DB::table($lockTable)->where('id', $lockId)->lockForUpdate()->first();
    try {
        for ($i = 0; $i < 2; $i++) {
            $marker = sys_get_temp_dir().'/sa-identity-'.bin2hex(random_bytes(8));
            $markers[] = $marker;
            $tag = 'sa-identity-'.bin2hex(random_bytes(8));
            $tags[] = $tag;
            $worker = new Process([PHP_BINARY, base_path('tests/Support/identity-race.php'), $mode, (string) $recipient->id, $secret, $marker, $tag], base_path(), [
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'simple_admin_testing', 'DB_URL' => '', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
            ]);
            $worker->setTimeout(30)->start();
            $workers[] = $worker;
        }
        $deadline = microtime(true) + 15;
        while (! file_exists($markers[0]) || ! file_exists($markers[1])) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Workers did not start.');
            } usleep(10000);
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
                throw new RuntimeException('Both credential requests must wait for the lock.');
            } usleep(10000);
        } while (true);
        DB::commit();
        $results = [];
        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput());
            $results[] = trim($worker->getOutput());
        }
        sort($results);
        expect($results)->toBe($mode === 'invitation' ? ['204', '410'] : ['204', '422']);
        if ($mode === 'invitation') {
            expect($invitation->refresh()->accepted_at)->not->toBeNull();
            $this->assertDatabaseCount('role_assignments', 2);
        } else {
            expect($recipient->refresh()->recoveryCodes())->not->toContain($secret);
        }
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
})->with(['invitation', 'mfa']);

<?php

use App\Enums\RoleName;
use App\Models\Membership;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

// Fixtures must commit so two independent database connections can observe them.
uses(DatabaseMigrations::class);

it('serializes concurrent owner changes and retains one active owner', function (string $operation) {
    [$owner, $organization] = tenantWorkspace();
    $second = tenantMember($owner, $organization, RoleName::Owner);
    $first = $organization->memberships()->where('user_id', $owner->id)->firstOrFail();
    $role = tenantRole($organization, RoleName::Owner);
    $workers = [];
    $markers = [];
    $tags = [];
    DB::beginTransaction();
    $organization->newQuery()->whereKey($organization->id)->lockForUpdate()->firstOrFail();

    try {
        foreach ([$first, $second] as $membership) {
            $marker = sys_get_temp_dir().'/simple-admin-owner-race-'.bin2hex(random_bytes(8));
            $markers[] = $marker;
            $tag = 'sa-owner-race-'.bin2hex(random_bytes(8));
            $tags[] = $tag;
            $worker = new Process([PHP_BINARY, base_path('tests/Support/owner-race.php'), (string) $membership->user_id,
                (string) $organization->id, (string) $membership->id, (string) $role->id, $operation, $marker, $tag], base_path(), [
                    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'simple_admin_testing',
                    'DB_URL' => '', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
                ]);
            $worker->setTimeout(30)->start();
            $workers[] = $worker;
        }
        $deadline = microtime(true) + 15;
        while (! file_exists($markers[0]) || ! file_exists($markers[1])) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Concurrent owner workers did not start.');
            }
            usleep(10000);
        }
        foreach ($markers as $marker) {
            touch($marker.'.go');
        }
        // Prove both actions are waiting on PostgreSQL row locks before releasing them.
        $deadline = microtime(true) + 15;
        do {
            DB::select('SELECT pg_stat_clear_snapshot()');
            $waiting = DB::selectOne("SELECT count(*) AS waiting FROM pg_stat_activity WHERE application_name IN (?, ?) AND wait_event_type = 'Lock'", $tags)->waiting;
            if ((int) $waiting === 2) {
                break;
            }
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Both owner changes must wait for the organization lock.');
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
        expect($results)->toBe(['blocked', 'changed']);
        expect(Membership::where('organization_id', $organization->id)->where('status', 'active')
            ->whereHas('roleAssignment.role', fn ($query) => $query->where('name', RoleName::Owner))->count())->toBe(1);
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
})->with(['remove', 'suspend']);

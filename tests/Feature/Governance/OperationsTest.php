<?php

use App\Governance\Health;
use App\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('exposes generic readiness and reports stale workers without connection details', function () {
    Event::dispatch(new Looping('redis', 'default'));
    expect(app(Health::class)->check())->toBe(['database' => true, 'cache' => true, 'worker' => true]);
    $this->get(route('health.ready'))->assertOk()->assertExactJson(['status' => 'ok']);
    Cache::forget('governance:worker-heartbeat');
    $this->get(route('health.ready'))->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
});

it('records sanitized tenant queue failures and does not expose other tenants incidents', function () {
    [$owner, $org] = tenantWorkspace();
    [, $other] = tenantWorkspace();
    tenantActor($owner, $org);
    $uuid = (string) Str::uuid();
    $correlation = (string) Str::uuid();
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn(['governance' => ['organization_id' => $org->id, 'correlation_id' => $correlation]]);
    $job->shouldReceive('uuid')->andReturn($uuid);
    Event::dispatch(new JobFailed('redis', $job, new RuntimeException('Secret credentials must never appear')));
    $this->assertDatabaseHas('queue_incidents', ['organization_id' => $org->id, 'job_uuid' => $uuid, 'exception_class' => 'RuntimeException']);
    $event = AuditEvent::where('event', 'queue.failed')->firstOrFail();
    expect($event->correlation_id)->toBe($correlation)->and(json_encode($event->changes))->not->toContain('Secret credentials');
    DB::table('queue_incidents')->insert(['organization_id' => $other->id, 'job_uuid' => (string) Str::uuid(), 'exception_class' => 'ForeignOnlyException', 'failed_at' => now()]);
    $this->get(route('dashboard'))->assertOk()->assertSee('RuntimeException')->assertDontSee('Secret credentials')->assertDontSee('ForeignOnlyException');
});

it('propagates queue correlation and clears it between worker iterations', function () {
    $id = (string) Str::uuid();
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn(['governance' => ['correlation_id' => $id]]);
    Event::dispatch(new JobProcessing('redis', $job));
    expect(request()->attributes->get('correlation_id'))->toBe($id);
    Event::dispatch(new Looping('redis', 'default'));
    expect(request()->attributes->has('correlation_id'))->toBeFalse();
    $next = Mockery::mock(Job::class);
    $next->shouldReceive('payload')->andReturn([]);
    Event::dispatch(new JobProcessing('redis', $next));
    expect(request()->attributes->get('correlation_id'))->not->toBe($id)
        ->and(Str::isUuid(request()->attributes->get('correlation_id')))->toBeTrue();
});

<?php

namespace App\Governance;

use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

class QueueVisibility
{
    public function register(): void
    {
        Queue::createPayloadUsing(function (): array {
            $user = auth()->user();
            $org = $user instanceof User ? app(TenantContext::class)->current($user)?->organization_id : null;

            return ['governance' => ['organization_id' => $org, 'correlation_id' => request()->attributes->get('correlation_id')]];
        });
        Queue::before(function (JobProcessing $event): void {
            $id = $event->job->payload()['governance']['correlation_id'] ?? null;
            $id = is_string($id) && Str::isUuid($id) ? $id : (string) Str::uuid();
            request()->attributes->set('correlation_id', $id);
            Log::withContext(['correlation_id' => $id]);
        });
        Queue::looping(function (): void {
            Log::withoutContext();
            request()->attributes->remove('correlation_id');
            Cache::put('governance:worker-heartbeat', time(), 120);
        });
        Queue::failing(function (JobFailed $event): void {
            $payload = $event->job->payload();
            $org = $payload['governance']['organization_id'] ?? null;
            $correlation = $payload['governance']['correlation_id'] ?? null;
            if (is_string($correlation) && Str::isUuid($correlation)) {
                request()->attributes->set('correlation_id', $correlation);
            }
            // The exception message and payload may contain credentials; never expose them.
            DB::table('queue_incidents')->insert([
                'organization_id' => is_int($org) ? $org : null,
                'job_uuid' => $event->job->uuid(), 'exception_class' => class_basename($event->exception),
                'failed_at' => now(),
            ]);
            app(Audit::class)->record('queue.failed', is_int($org) ? $org : null, 'QueueJob', $event->job->uuid());
            Log::channel('security')->error('queue.failed', ['job_uuid' => $event->job->uuid(), 'organization_id' => $org]);
        });
    }
}

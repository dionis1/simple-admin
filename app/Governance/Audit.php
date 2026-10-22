<?php

namespace App\Governance;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Audit
{
    /** @param array<string, mixed> $changes */
    public function record(string $event, ?int $organizationId, string $targetType, int|string|null $targetId, array $changes = [], ?User $actor = null): AuditEvent
    {
        // Values are structural identifiers/states only. Never serialize requests or models.
        $safe = [];
        foreach ($changes as $key => $value) {
            $safe[$key] = $this->redact($key, $value);
        }
        $actor ??= auth()->user() instanceof User ? auth()->user() : null;
        $correlation = request()->attributes->get('correlation_id');
        if (! is_string($correlation) || ! Str::isUuid($correlation)) {
            $correlation = (string) Str::uuid();
            request()->attributes->set('correlation_id', $correlation);
        }
        $entry = new AuditEvent;
        $entry->forceFill([
            'event' => $event, 'organization_id' => $organizationId, 'actor_id' => $actor?->id,
            'subject_user_id' => request()->attributes->get('support_subject_id'),
            'target_type' => $targetType, 'target_id' => $targetId === null ? null : (string) $targetId,
            'correlation_id' => $correlation, 'changes' => $safe, 'occurred_at' => now(),
        ])->save();

        DB::afterCommit(function () use ($entry): void {
            Log::channel('security')->info($entry->event, ['audit_id' => $entry->id, 'organization_id' => $entry->organization_id, 'actor_id' => $entry->actor_id, 'correlation_id' => $entry->correlation_id]);
        });

        return $entry;
    }

    private function redact(string $key, mixed $value): mixed
    {
        $ids = ['role_id', 'department_id', 'owner_membership_id', 'membership_id', 'team_id', 'count', 'approval_id', 'grant_id', 'target_membership_id'];
        $dates = ['expires_at', 'started_at', 'ended_at', 'accepted_at', 'revoked_at'];
        $states = ['status' => ['active', 'suspended', 'deactivated'], 'state' => ['pending', 'approved', 'executed', 'rejected', 'cancelled', 'expired'], 'operation' => ['support_access', 'revoke_org_tokens']];
        if (! in_array($key, [...$ids, ...$dates, ...array_keys($states)], true)) {
            return '[REDACTED]';
        }
        if (is_array($value)) {
            if (array_keys($value) !== ['before', 'after'] || is_array($value['before']) || is_array($value['after'])) {
                return '[REDACTED]';
            }

            return ['before' => $this->redact($key, $value['before']), 'after' => $this->redact($key, $value['after'])];
        }
        if ($value === null) {
            return null;
        }
        if (in_array($key, $ids, true)) {
            return is_int($value) && $value >= 0 ? $value : '[REDACTED]';
        }
        if (in_array($key, $dates, true)) {
            return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})?$/D', $value) === 1 ? $value : '[REDACTED]';
        }

        return in_array($value, $states[$key], true) ? $value : '[REDACTED]';
    }
}

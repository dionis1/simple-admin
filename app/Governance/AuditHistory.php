<?php

namespace App\Governance;

use App\Models\AuditEvent;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class AuditHistory
{
    /** @return Builder<AuditEvent> */
    public function query(User $actor, string $search = ''): Builder
    {
        Gate::forUser($actor)->authorize('viewAny', AuditEvent::class);
        Validator::make(['search' => $search], ['search' => ['string', 'max:100']])->validate();
        $org = app(TenantContext::class)->resolve($actor)->organization_id;
        $query = AuditEvent::where('organization_id', $org);
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('event', 'ilike', '%'.$search.'%')->orWhere('target_type', 'ilike', '%'.$search.'%')->orWhere('target_id', 'ilike', '%'.$search.'%')->orWhereRaw('correlation_id::text ILIKE ?', ['%'.$search.'%'])->orWhereRaw('actor_id::text = ?', [$search]));
        }

        return $query->orderByDesc('id');
    }

    public function cell(string $value): string
    {
        // Defend spreadsheet consumers even if future event data contains user text.
        return preg_match('/^[\s\x00-\x1f]*[=+\-@]/u', $value) === 1 || preg_match('/^[\t\r\n]/', $value) === 1 ? "'".$value : $value;
    }
}

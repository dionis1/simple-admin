<?php

namespace App\Governance;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditObserver
{
    public function created(Model $model): void
    {
        $this->write($model, 'created', $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $this->write($model, 'updated', $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        $this->write($model, 'deleted', $model->getAttributes());
    }

    /** @param array<string, mixed> $attributes */
    private function write(Model $model, string $verb, array $attributes): void
    {
        unset($attributes['id'], $attributes['organization_id'], $attributes['created_at'], $attributes['updated_at'], $attributes['last_used_at']);
        if ($verb === 'updated' && $attributes === []) {
            return;
        }
        $changes = [];
        foreach ($attributes as $key => $value) {
            // Before/after structural values; personal text/secrets are redacted by Audit.
            $changes[$key] = ['before' => $verb === 'created' ? null : $model->getRawOriginal($key), 'after' => $value];
        }
        $org = $model instanceof Organization ? (int) $model->getKey() : $model->getAttribute('organization_id');
        if ($model instanceof User) {
            // Global account events are not attributed to an arbitrary selected tenant.
            $org = null;
        }
        app(Audit::class)->record(Str::snake(class_basename($model)).'.'.$verb, is_numeric($org) ? (int) $org : null, class_basename($model), (string) $model->getKey(), $changes);
    }
}

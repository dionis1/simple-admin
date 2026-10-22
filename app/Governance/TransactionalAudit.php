<?php

namespace App\Governance;

use Illuminate\Database\Eloquent\Model;

trait TransactionalAudit
{
    public static function bootTransactionalAudit(): void
    {
        static::created(function (Model $model): void {
            app(AuditObserver::class)->created($model);
        });
        static::updated(function (Model $model): void {
            app(AuditObserver::class)->updated($model);
        });
        static::deleted(function (Model $model): void {
            app(AuditObserver::class)->deleted($model);
        });
    }

    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(fn (): bool => parent::save($options));
    }

    public function delete(): ?bool
    {
        return $this->getConnection()->transaction(fn (): ?bool => parent::delete());
    }
}

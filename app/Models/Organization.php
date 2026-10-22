<?php

namespace App\Models;

use App\Enums\AccessStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property AccessStatus $status
 */
class Organization extends Model
{
    protected $fillable = ['name'];

    protected function casts(): array
    {
        return ['status' => AccessStatus::class];
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return HasMany<Role, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }
}

<?php

namespace App\Models;

use App\Governance\TransactionalAudit;
use Illuminate\Database\Eloquent\Model;

/** @property int $id
 * @property int $organization_id
 * @property string $name
 */
class Department extends Model
{
    use TransactionalAudit;

    protected $guarded = ['*'];
}

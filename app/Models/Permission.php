<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'system.permissions';

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'system.role_permissions',
            'permission_id',
            'role_id'
        )->withTimestamps();
    }
}

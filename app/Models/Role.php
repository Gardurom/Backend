<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'system.roles';

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'system.user_roles',
            'role_id',
            'user_id'
        )->withTimestamps();
    }
}

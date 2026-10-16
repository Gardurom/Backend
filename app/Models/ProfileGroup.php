<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfileGroup extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'system.profile_groups';

    public function profiles(): HasMany
    {
        return $this->hasMany(
            Profile::class,
            'profile_group_id',
            'id'
        );
    }
}

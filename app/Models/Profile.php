<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Profile extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'system.profiles';

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'system.user_profiles',
            'profile_id',
            'user_id'
        )
            ->withPivot('is_default')
            ->withTimestamps();
    }
}

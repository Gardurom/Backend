<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AcademicSite extends Model
{
    protected $table = 'institutional.academic_sites';

    protected $primaryKey = 'id_sede';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'codigo',
        'nombre',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    public function cohortAssignments(): HasMany
    {
        return $this->hasMany(
            AcademicCohortSiteHistory::class,
            'id_sede',
            'id_sede'
        );
    }

    public function groupAssignments(): HasMany
    {
        return $this->hasMany(
            AcademicGroupSiteHistory::class,
            'id_sede',
            'id_sede'
        );
    }
}
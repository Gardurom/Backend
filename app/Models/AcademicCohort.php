<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id_programa', 'tipo', 'numero', 'anio'])]
class AcademicCohort extends Model
{
    protected $table = 'institutional.academic_cohorts';

    protected $primaryKey = 'id_cohorte';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function program(): BelongsTo
    {
        return $this->belongsTo(
            AcademicProgram::class,
            'id_programa',
            'id_programa'
        );
    }

    public function groups(): HasMany
    {
        return $this->hasMany(
            AcademicGroup::class,
            'id_cohorte',
            'id_cohorte'
        );
    }

    public function siteAssignments(): HasMany
    {
        return $this->hasMany(
            AcademicCohortSiteHistory::class,
            'id_cohorte',
            'id_cohorte'
        );
    }
}
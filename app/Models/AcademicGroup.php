<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AcademicGroup extends Model
{
    protected $table = 'institutional.academic_groups';

    protected $primaryKey = 'id_grupo';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'id_cohorte',
        'codigo',
        'especializacion',
    ];

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(
            AcademicCohort::class,
            'id_cohorte',
            'id_cohorte'
        );
    }

    public function siteAssignments(): HasMany
    {
        return $this->hasMany(
            AcademicGroupSiteHistory::class,
            'id_grupo',
            'id_grupo'
        );
    }
}
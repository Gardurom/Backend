<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AcademicCohortSiteHistory extends Model
{
    protected $table = 'institutional.academic_cohort_site_history';

    protected $primaryKey = 'id_movimiento';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'id_cohorte',
        'id_sede',
        'vigente_desde',
        'vigente_hasta',
    ];

    protected function casts(): array
    {
        return [
            'vigente_desde' => 'immutable_datetime',
            'vigente_hasta' => 'immutable_datetime',
        ];
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(
            AcademicCohort::class,
            'id_cohorte',
            'id_cohorte'
        );
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(
            AcademicSite::class,
            'id_sede',
            'id_sede'
        );
    }
}
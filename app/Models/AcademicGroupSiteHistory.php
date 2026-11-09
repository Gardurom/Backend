<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AcademicGroupSiteHistory extends Model
{
    protected $table = 'institutional.academic_group_site_history';

    protected $primaryKey = 'id_movimiento';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'id_grupo',
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

    public function group(): BelongsTo
    {
        return $this->belongsTo(
            AcademicGroup::class,
            'id_grupo',
            'id_grupo'
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
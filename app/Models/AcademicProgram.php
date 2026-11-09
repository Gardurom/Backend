<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'nombre'])]
class AcademicProgram extends Model
{
    protected $table = 'institutional.academic_programs';

    protected $primaryKey = 'id_programa';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function cohorts(): HasMany
    {
        return $this->hasMany(
            AcademicCohort::class,
            'id_programa',
            'id_programa'
        );
    }
}
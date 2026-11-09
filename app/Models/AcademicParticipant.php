<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id_persona'])]
class AcademicParticipant extends Model
{
    protected $table = 'institutional.academic_participants';

    protected $primaryKey = 'id_participante';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function person(): BelongsTo
    {
        return $this->belongsTo(
            Person::class,
            'id_persona',
            'id_persona'
        );
    }
}

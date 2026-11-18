<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionalCredential extends Model
{
    protected $table = 'institutional.credentials';

    protected $primaryKey = 'id_credencial';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'vigente_desde' => 'datetime',
            'vigente_hasta' => 'datetime',
            'revocada_en' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(
            Person::class,
            'id_persona',
            'id_persona'
        );
    }
}
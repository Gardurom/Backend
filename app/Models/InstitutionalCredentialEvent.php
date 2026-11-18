<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionalCredentialEvent extends Model
{
    protected $table = 'institutional.credential_events';

    protected $primaryKey = 'id_evento';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'ocurrido_en' => 'immutable_datetime',
        ];
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(
            InstitutionalCredential::class,
            'id_credencial',
            'id_credencial'
        );
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_usuario_actor',
            'id'
        );
    }
}
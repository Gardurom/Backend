<?php

namespace App\Models;

use App\Importing\ImportBatchStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'import_type',
    'status',
    'original_filename',
    'storage_disk',
    'storage_path',
    'file_sha256',
    'options',
    'total_rows',
    'valid_rows',
    'invalid_rows',
    'processed_rows',
    'failed_rows',
    'created_by',
    'confirmed_at',
    'started_at',
    'finished_at',
])]
class ImportBatch extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'system.import_batches';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportBatchStatus::class,
            'options' => 'array',
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'invalid_rows' => 'integer',
            'processed_rows' => 'integer',
            'failed_rows' => 'integer',
            'created_by' => 'integer',
            'confirmed_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by',
            'id'
        );
    }

    public function rows(): HasMany
    {
        return $this->hasMany(
            ImportRow::class,
            'import_batch_id',
            'id'
        );
    }
}

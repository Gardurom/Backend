<?php

namespace App\Models;

use App\Importing\ImportRowStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'import_batch_id',
    'row_number',
    'status',
    'raw_data',
    'normalized_data',
    'validation_errors',
    'result_data',
])]
class ImportRow extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'system.import_rows';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'import_batch_id' => 'integer',
            'row_number' => 'integer',
            'status' => ImportRowStatus::class,
            'raw_data' => 'array',
            'normalized_data' => 'array',
            'validation_errors' => 'array',
            'result_data' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(
            ImportBatch::class,
            'import_batch_id',
            'id'
        );
    }
}

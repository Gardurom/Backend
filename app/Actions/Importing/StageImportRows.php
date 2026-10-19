<?php

namespace App\Actions\Importing;

use App\Importing\ImportBatchStatus;
use App\Importing\ImportSourceRow;
use App\Importing\InvalidImportBatchStaging;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StageImportRows
{
    private const INSERT_CHUNK_SIZE = 500;

    /**
     * @param  iterable<ImportSourceRow>  $source
     */
    public function execute(
        int $batchId,
        iterable $source
    ): ImportBatch {
        return DB::connection()->transaction(
            function () use ($batchId, $source): ImportBatch {
                $batch = ImportBatch::query()
                    ->lockForUpdate()
                    ->findOrFail($batchId);

                if ($batch->status !== ImportBatchStatus::UPLOADED) {
                    throw new InvalidImportBatchStaging(
                        'Solo un lote UPLOADED puede entrar a staging.'
                    );
                }

                if (
                    $batch->total_rows !== 0
                    || $batch->rows()->exists()
                ) {
                    throw new InvalidImportBatchStaging(
                        'El lote ya contiene filas en staging.'
                    );
                }

                $count = 0;
                $pending = [];
                $now = now();

                foreach ($source as $row) {
                    if (! $row instanceof ImportSourceRow) {
                        throw new InvalidArgumentException(
                            'La fuente debe producir instancias de ImportSourceRow.'
                        );
                    }

                    $pending[] = [
                        'import_batch_id' => $batch->id,
                        'row_number' => $row->rowNumber,
                        'raw_data' => json_encode(
                            $row->rawData,
                            JSON_THROW_ON_ERROR
                        ),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $count++;

                    if (
                        count($pending)
                        === self::INSERT_CHUNK_SIZE
                    ) {
                        $this->insertRows(
                            $pending
                        );

                        $pending = [];
                    }
                }

                if ($count === 0) {
                    throw new InvalidImportBatchStaging(
                        'El lote debe contener al menos una fila fuente.'
                    );
                }

                if ($pending !== []) {
                    $this->insertRows(
                        $pending
                    );
                }

                $batch->total_rows = $count;
                $batch->save();

                return $batch->refresh();
            }
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertRows(
        array $rows
    ): void {
        DB::table(
            'system.import_rows'
        )->insert($rows);
    }
}

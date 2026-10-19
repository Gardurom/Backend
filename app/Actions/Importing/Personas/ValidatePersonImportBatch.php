<?php

namespace App\Actions\Importing\Personas;

use App\Actions\Importing\TransitionImportBatch;
use App\Importing\ImportBatchStatus;
use App\Importing\ImportRowStatus;
use App\Importing\InvalidImportBatchValidation;
use App\Importing\Personas\PersonImportRowValidationPipeline;
use App\Importing\Personas\PersonImportTemplateV2;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ValidatePersonImportBatch
{
    private const ROW_CHUNK_SIZE = 100;

    public function __construct(
        private readonly PersonImportRowValidationPipeline $pipeline,
        private readonly TransitionImportBatch $transitionBatch,
        private readonly PersonImportTemplateV2 $template
    ) {}

    public function execute(
        int $batchId
    ): ImportBatch {
        $this->claimBatch(
            $batchId
        );

        try {
            $this->validatePendingRows(
                $batchId
            );

            return $this->finishBatch(
                $batchId
            );
        } catch (Throwable $error) {
            $this->transitionBatch->execute(
                $batchId,
                ImportBatchStatus::FAILED
            );

            throw $error;
        }
    }

    private function claimBatch(
        int $batchId
    ): void {
        DB::connection()->transaction(
            function () use ($batchId): void {
                $batch = ImportBatch::query()
                    ->lockForUpdate()
                    ->findOrFail($batchId);

                if ($batch->import_type !== 'PERSONAS') {
                    throw new InvalidImportBatchValidation(
                        'El lote no corresponde a una importacion de PERSONAS.'
                    );
                }

                if ($batch->status !== ImportBatchStatus::UPLOADED) {
                    throw new InvalidImportBatchValidation(
                        'Solo un lote UPLOADED puede iniciar validacion.'
                    );
                }

                if (
                    $batch->total_rows < 1
                    || ! $batch->rows()->exists()
                ) {
                    throw new InvalidImportBatchValidation(
                        'El lote no contiene filas para validar.'
                    );
                }

                $sourceColumns =
                    $batch->options['source_columns']
                    ?? null;

                if (
                    ! is_array($sourceColumns)
                    || array_values($sourceColumns)
                        !== $this->template->columns()
                ) {
                    throw new InvalidImportBatchValidation(
                        'Las columnas fuente no coinciden con la plantilla PERSONAS v2.'
                    );
                }

                $batch->status =
                    ImportBatchStatus::VALIDATING;

                $batch->valid_rows = 0;
                $batch->invalid_rows = 0;

                $batch->save();
            }
        );
    }

    private function validatePendingRows(
        int $batchId
    ): void {
        ImportRow::query()
            ->where(
                'import_batch_id',
                $batchId
            )
            ->where(
                'status',
                ImportRowStatus::PENDING
            )
            ->orderBy('id')
            ->chunkById(
                self::ROW_CHUNK_SIZE,
                function (
                    Collection $rows
                ): void {
                    DB::connection()->transaction(
                        function () use ($rows): void {
                            foreach ($rows as $row) {
                                $result =
                                    $this->pipeline->validate(
                                        $row->raw_data
                                    );

                                $row->status =
                                    $result->isValid()
                                        ? ImportRowStatus::VALID
                                        : ImportRowStatus::INVALID;

                                $row->normalized_data =
                                    $result->normalizedData;

                                $row->validation_errors =
                                    $result->errors;

                                $row->save();
                            }
                        }
                    );
                },
                'id'
            );
    }

    private function finishBatch(
        int $batchId
    ): ImportBatch {
        return DB::connection()->transaction(
            function () use ($batchId): ImportBatch {
                $batch = ImportBatch::query()
                    ->lockForUpdate()
                    ->findOrFail($batchId);

                if ($batch->status !== ImportBatchStatus::VALIDATING) {
                    throw new InvalidImportBatchValidation(
                        'El lote debe permanecer VALIDATING hasta finalizar la validacion.'
                    );
                }

                $validRows = ImportRow::query()
                    ->where(
                        'import_batch_id',
                        $batch->id
                    )
                    ->where(
                        'status',
                        ImportRowStatus::VALID
                    )
                    ->count();

                $invalidRows = ImportRow::query()
                    ->where(
                        'import_batch_id',
                        $batch->id
                    )
                    ->where(
                        'status',
                        ImportRowStatus::INVALID
                    )
                    ->count();

                $pendingRows = ImportRow::query()
                    ->where(
                        'import_batch_id',
                        $batch->id
                    )
                    ->where(
                        'status',
                        ImportRowStatus::PENDING
                    )
                    ->count();

                if ($pendingRows !== 0) {
                    throw new InvalidImportBatchValidation(
                        'El lote conserva filas PENDING despues de la validacion.'
                    );
                }

                if (
                    $validRows + $invalidRows
                    !== $batch->total_rows
                ) {
                    throw new InvalidImportBatchValidation(
                        'Los resultados de validacion no coinciden con el total del lote.'
                    );
                }

                $batch->valid_rows =
                    $validRows;

                $batch->invalid_rows =
                    $invalidRows;

                $batch->status =
                    ImportBatchStatus::READY;

                $batch->save();

                return $batch->refresh();
            }
        );
    }
}

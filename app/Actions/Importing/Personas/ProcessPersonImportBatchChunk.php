<?php

namespace App\Actions\Importing\Personas;

use App\Actions\Importing\TransitionImportBatch;
use App\Actions\Persona\RegisterPerson;
use App\Importing\ImportBatchStatus;
use App\Importing\ImportRowStatus;
use App\Importing\InvalidImportBatchProcessing;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class ProcessPersonImportBatchChunk
{
    private const CHUNK_SIZE = 100;

    /**
     * @var array<string, string>
     */
    private const UNIQUE_CONSTRAINT_FIELDS = [
        'institutional_persons_curp_unique' => 'curp',
        'institutional_persons_rfc_unique' => 'rfc',
        'persons_correo_institucional_unique' => 'correo_institucional',
    ];

    public function __construct(
        private readonly RegisterPerson $registerPerson,
        private readonly TransitionImportBatch $transitionBatch
    ) {}

    public function execute(
        int $batchId
    ): ImportBatch {
        $batch =
            $this->claimBatch(
                $batchId
            );

        if (
            $batch->status
            === ImportBatchStatus::COMPLETED
        ) {
            return $batch;
        }

        $rowIds = ImportRow::query()
            ->where(
                'import_batch_id',
                $batchId
            )
            ->where(
                'status',
                ImportRowStatus::VALID
            )
            ->orderBy('id')
            ->limit(
                self::CHUNK_SIZE
            )
            ->pluck('id');

        foreach ($rowIds as $rowId) {
            $this->processRow(
                $batchId,
                (int) $rowId,
                $batch->created_by
            );
        }

        return $this->finishIfComplete(
            $batchId
        );
    }

    private function claimBatch(
        int $batchId
    ): ImportBatch {
        return DB::connection()->transaction(
            function () use ($batchId): ImportBatch {
                $batch = ImportBatch::query()
                    ->lockForUpdate()
                    ->findOrFail($batchId);

                if ($batch->import_type !== 'PERSONAS') {
                    throw new InvalidImportBatchProcessing(
                        'El lote no corresponde a una importacion de PERSONAS.'
                    );
                }

                if (
                    $batch->status
                    === ImportBatchStatus::COMPLETED
                ) {
                    return $batch->refresh();
                }

                if (
                    $batch->status
                    === ImportBatchStatus::PROCESSING
                ) {
                    return $batch->refresh();
                }

                if (
                    $batch->status
                    !== ImportBatchStatus::QUEUED
                ) {
                    throw new InvalidImportBatchProcessing(
                        'Solo un lote QUEUED o PROCESSING puede ser procesado.'
                    );
                }

                return $this->transitionBatch
                    ->execute(
                        $batchId,
                        ImportBatchStatus::PROCESSING
                    );
            }
        );
    }

    private function processRow(
        int $batchId,
        int $rowId,
        int $userId
    ): void {
        try {
            DB::connection()->transaction(
                function () use (
                    $batchId,
                    $rowId,
                    $userId
                ): void {
                    $row = ImportRow::query()
                        ->where(
                            'import_batch_id',
                            $batchId
                        )
                        ->lockForUpdate()
                        ->findOrFail(
                            $rowId
                        );

                    if (
                        $row->status
                        !== ImportRowStatus::VALID
                    ) {
                        return;
                    }

                    $normalizedData =
                        $row->normalized_data
                        ?? [];

                    $person =
                        $this->registerPerson
                            ->execute(
                                $userId,
                                $normalizedData
                            );

                    $row->status =
                        ImportRowStatus::PROCESSED;

                    $row->result_data = [
                        'id_persona' => (string) $person->getKey(),
                        'num_expediente' => $person->num_expediente,
                    ];

                    $row->save();

                    ImportBatch::query()
                        ->whereKey(
                            $batchId
                        )
                        ->increment(
                            'processed_rows'
                        );
                }
            );
        } catch (QueryException $error) {
            $field =
                $this->knownUniquenessConflictField(
                    $error
                );

            if ($field === null) {
                throw $error;
            }

            $this->markUniquenessFailure(
                $batchId,
                $rowId,
                $field
            );
        }
    }

    private function markUniquenessFailure(
        int $batchId,
        int $rowId,
        string $field
    ): void {
        DB::connection()->transaction(
            function () use (
                $batchId,
                $rowId,
                $field
            ): void {
                $row = ImportRow::query()
                    ->where(
                        'import_batch_id',
                        $batchId
                    )
                    ->lockForUpdate()
                    ->findOrFail(
                        $rowId
                    );

                if (
                    $row->status
                    !== ImportRowStatus::VALID
                ) {
                    return;
                }

                $row->status =
                    ImportRowStatus::FAILED;

                $row->result_data = [
                    'error_code' => 'PERSON_UNIQUENESS_CONFLICT',
                    'field' => $field,
                ];

                $row->save();

                ImportBatch::query()
                    ->whereKey(
                        $batchId
                    )
                    ->increment(
                        'failed_rows'
                    );
            }
        );
    }

    private function finishIfComplete(
        int $batchId
    ): ImportBatch {
        return DB::connection()->transaction(
            function () use ($batchId): ImportBatch {
                $batch = ImportBatch::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $batchId
                    );

                if (
                    $batch->status
                    === ImportBatchStatus::COMPLETED
                ) {
                    return $batch->refresh();
                }

                if (
                    $batch->status
                    !== ImportBatchStatus::PROCESSING
                ) {
                    throw new InvalidImportBatchProcessing(
                        'El lote debe permanecer PROCESSING durante su procesamiento.'
                    );
                }

                $remainingRows =
                    ImportRow::query()
                        ->where(
                            'import_batch_id',
                            $batchId
                        )
                        ->where(
                            'status',
                            ImportRowStatus::VALID
                        )
                        ->exists();

                if ($remainingRows) {
                    return $batch->refresh();
                }

                $processedRows =
                    ImportRow::query()
                        ->where(
                            'import_batch_id',
                            $batchId
                        )
                        ->where(
                            'status',
                            ImportRowStatus::PROCESSED
                        )
                        ->count();

                $failedRows =
                    ImportRow::query()
                        ->where(
                            'import_batch_id',
                            $batchId
                        )
                        ->where(
                            'status',
                            ImportRowStatus::FAILED
                        )
                        ->count();

                if (
                    $processedRows + $failedRows
                    !== $batch->valid_rows
                ) {
                    throw new InvalidImportBatchProcessing(
                        'El resultado del procesamiento no coincide con las filas validas del lote.'
                    );
                }

                $batch->processed_rows =
                    $processedRows;

                $batch->failed_rows =
                    $failedRows;

                $batch->save();

                return $this->transitionBatch
                    ->execute(
                        $batchId,
                        ImportBatchStatus::COMPLETED
                    );
            }
        );
    }

    private function knownUniquenessConflictField(
        QueryException $error
    ): ?string {
        $sqlState =
            $error->errorInfo[0]
            ?? (string) $error->getCode();

        if ($sqlState !== '23505') {
            return null;
        }

        $message =
            $error->errorInfo[2]
            ?? $error->getMessage();

        foreach (
            self::UNIQUE_CONSTRAINT_FIELDS as $constraint => $field
        ) {
            if (
                str_contains(
                    $message,
                    $constraint
                )
            ) {
                return $field;
            }
        }

        return null;
    }
}

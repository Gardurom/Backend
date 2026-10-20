<?php

namespace App\Actions\Importing\Personas;

use App\Actions\Importing\TransitionImportBatch;
use App\Importing\ImportBatchStatus;
use App\Importing\InvalidImportBatchConfirmation;
use App\Jobs\Importing\Personas\ProcessPersonImportBatchJob;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

final class ConfirmPersonImportBatch
{
    public function __construct(
        private readonly TransitionImportBatch $transitionBatch
    ) {}

    public function execute(
        int $batchId
    ): ImportBatch {
        return DB::connection()->transaction(
            function () use ($batchId): ImportBatch {
                $batch = ImportBatch::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $batchId
                    );

                if ($batch->import_type !== 'PERSONAS') {
                    throw new InvalidImportBatchConfirmation(
                        'El lote no corresponde a una importacion de PERSONAS.'
                    );
                }

                if (
                    $batch->status
                    !== ImportBatchStatus::READY
                ) {
                    throw new InvalidImportBatchConfirmation(
                        'Solo un lote READY puede ser confirmado.'
                    );
                }

                $queued =
                    $this->transitionBatch
                        ->execute(
                            $batchId,
                            ImportBatchStatus::QUEUED
                        );

                Queue::connection(
                    'database'
                )->push(
                    new ProcessPersonImportBatchJob(
                        $batchId
                    )
                );

                return $queued->refresh();
            }
        );
    }
}

<?php

namespace App\Actions\Importing;

use App\Importing\ImportBatchStatus;
use App\Importing\InvalidImportBatchTransition;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;

class TransitionImportBatch
{
    public function execute(
        int $batchId,
        ImportBatchStatus $target
    ): ImportBatch {
        return DB::connection()->transaction(
            function () use ($batchId, $target): ImportBatch {
                $batch = ImportBatch::query()
                    ->lockForUpdate()
                    ->findOrFail($batchId);

                $current = $batch->status;

                if (! $current->canTransitionTo($target)) {
                    throw new InvalidImportBatchTransition(
                        $current,
                        $target
                    );
                }

                $now = now();

                $attributes = [
                    'status' => $target,
                ];

                if ($target === ImportBatchStatus::QUEUED) {
                    $attributes['confirmed_at'] =
                        $batch->confirmed_at ?? $now;
                }

                if ($target === ImportBatchStatus::PROCESSING) {
                    $attributes['started_at'] =
                        $batch->started_at ?? $now;
                }

                if (
                    in_array(
                        $target,
                        [
                            ImportBatchStatus::COMPLETED,
                            ImportBatchStatus::FAILED,
                            ImportBatchStatus::CANCELLED,
                        ],
                        true
                    )
                ) {
                    $attributes['finished_at'] =
                        $batch->finished_at ?? $now;
                }

                $batch->fill($attributes);
                $batch->save();

                return $batch->refresh();
            }
        );
    }
}

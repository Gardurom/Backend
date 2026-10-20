<?php

namespace App\Jobs\Importing\Personas;

use App\Actions\Importing\Personas\ProcessPersonImportBatchChunk;
use App\Actions\Importing\TransitionImportBatch;
use App\Importing\ImportBatchStatus;
use App\Models\ImportBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessPersonImportBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(
        public readonly int $batchId
    ) {
        $this->onConnection(
            'database'
        );
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [
            5,
            15,
            30,
            60,
        ];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping(
                "person-import-batch:{$this->batchId}"
            ))
                ->dontRelease()
                ->expireAfter(80),
        ];
    }

    public function handle(
        ProcessPersonImportBatchChunk $processor
    ): void {
        $batch =
            $processor->execute(
                $this->batchId
            );

        if (
            $batch->status
            !== ImportBatchStatus::PROCESSING
        ) {
            return;
        }

        self::dispatch(
            $this->batchId
        )->delay(
            now()->addSecond()
        );
    }

    public function failed(
        ?Throwable $error
    ): void {
        try {
            $batch =
                ImportBatch::query()
                    ->find(
                        $this->batchId
                    );

            if ($batch === null) {
                return;
            }

            if (
                ! in_array(
                    $batch->status,
                    [
                        ImportBatchStatus::QUEUED,
                        ImportBatchStatus::PROCESSING,
                    ],
                    true
                )
            ) {
                return;
            }

            app(
                TransitionImportBatch::class
            )->execute(
                $this->batchId,
                ImportBatchStatus::FAILED
            );
        } catch (Throwable $failureMarkerError) {
            report(
                $failureMarkerError
            );
        }
    }
}

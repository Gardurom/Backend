<?php

namespace Tests\Functional\Importing;

use App\Actions\Importing\Personas\ConfirmPersonImportBatch;
use App\Actions\Importing\TransitionImportBatch;
use App\Importing\ImportBatchStatus;
use App\Importing\InvalidImportBatchConfirmation;
use App\Jobs\Importing\Personas\ProcessPersonImportBatchJob;
use App\Models\ImportBatch;
use DomainException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Functional\FunctionalTestCase;

class ConfirmPersonImportBatchTest extends FunctionalTestCase
{
    public function test_confirms_ready_person_batch_and_dispatches_first_job(): void
    {
        Queue::fake();

        $batch = $this->createReadyBatch(
            validRows: 2,
            invalidRows: 1
        );

        $confirmed =
            $this->app->make(
                ConfirmPersonImportBatch::class
            )->execute(
                $batch->id
            );

        self::assertSame(
            ImportBatchStatus::QUEUED,
            $confirmed->status
        );

        self::assertNotNull(
            $confirmed->confirmed_at
        );

        self::assertSame(
            3,
            $confirmed->total_rows
        );

        self::assertSame(
            2,
            $confirmed->valid_rows
        );

        self::assertSame(
            1,
            $confirmed->invalid_rows
        );

        self::assertSame(
            0,
            $confirmed->processed_rows
        );

        self::assertSame(
            0,
            $confirmed->failed_rows
        );

        Queue::assertPushed(
            ProcessPersonImportBatchJob::class,
            1
        );

        Queue::assertPushed(
            ProcessPersonImportBatchJob::class,
            static fn (
                ProcessPersonImportBatchJob $job
            ): bool => $job->batchId
                === $batch->id
        );
    }

    public function test_rejects_ready_batch_without_valid_rows(): void
    {
        Queue::fake();

        $batch = $this->createReadyBatch(
            validRows: 0,
            invalidRows: 2
        );

        try {
            $this->app->make(
                ConfirmPersonImportBatch::class
            )->execute(
                $batch->id
            );
        } catch (InvalidImportBatchConfirmation $exception) {
            self::assertSame(
                'El lote debe contener al menos una fila valida para ser confirmado.',
                $exception->getMessage()
            );

            $batch->refresh();

            self::assertSame(
                ImportBatchStatus::READY,
                $batch->status
            );

            self::assertSame(
                0,
                $batch->valid_rows
            );

            self::assertSame(
                2,
                $batch->invalid_rows
            );

            self::assertNull(
                $batch->confirmed_at
            );

            Queue::assertNothingPushed();

            return;
        }

        self::fail(
            'Un lote sin filas validas no debe entrar a procesamiento.'
        );
    }

    public function test_rejects_other_import_type_without_dispatch(): void
    {
        Queue::fake();

        $batch = $this->createReadyBatch(
            validRows: 1,
            importType: 'OTRO'
        );

        try {
            $this->app->make(
                ConfirmPersonImportBatch::class
            )->execute(
                $batch->id
            );
        } catch (DomainException) {
            $batch->refresh();

            self::assertSame(
                ImportBatchStatus::READY,
                $batch->status
            );

            self::assertNull(
                $batch->confirmed_at
            );

            Queue::assertNothingPushed();

            return;
        }

        self::fail(
            'Un confirmador PERSONAS no debe aceptar otro tipo de importación.'
        );
    }

    public function test_rejects_batch_that_is_not_ready_without_duplicate_dispatch(): void
    {
        Queue::fake();

        $batch = $this->createReadyBatch(
            validRows: 1
        );

        $batch =
            (new TransitionImportBatch)
                ->execute(
                    $batch->id,
                    ImportBatchStatus::QUEUED
                );

        self::assertSame(
            ImportBatchStatus::QUEUED,
            $batch->status
        );

        try {
            $this->app->make(
                ConfirmPersonImportBatch::class
            )->execute(
                $batch->id
            );
        } catch (DomainException) {
            $batch->refresh();

            self::assertSame(
                ImportBatchStatus::QUEUED,
                $batch->status
            );

            Queue::assertNothingPushed();

            return;
        }

        self::fail(
            'Un lote ya confirmado no debe confirmarse nuevamente.'
        );
    }

    public function test_queue_failure_rolls_back_confirmation(): void
    {
        $batch = $this->createReadyBatch(
            validRows: 1
        );

        $databaseQueue = \Mockery::mock(
            \Illuminate\Contracts\Queue\Queue::class
        );

        $databaseQueue
            ->shouldReceive('push')
            ->once()
            ->andThrow(
                new RuntimeException(
                    'FALLO CONTROLADO DE COLA'
                )
            );

        Queue::shouldReceive('connection')
            ->once()
            ->with('database')
            ->andReturn(
                $databaseQueue
            );

        try {
            $this->app->make(
                ConfirmPersonImportBatch::class
            )->execute(
                $batch->id
            );
        } catch (RuntimeException $error) {
            self::assertSame(
                'FALLO CONTROLADO DE COLA',
                $error->getMessage()
            );

            $batch->refresh();

            self::assertSame(
                ImportBatchStatus::READY,
                $batch->status
            );

            self::assertNull(
                $batch->confirmed_at
            );

            self::assertSame(
                0,
                $batch->processed_rows
            );

            self::assertSame(
                0,
                $batch->failed_rows
            );

            return;
        }

        self::fail(
            'El fallo al encolar debe revertir la confirmación.'
        );
    }

    public function test_database_queue_is_part_of_confirmation_transaction(): void
    {
        self::assertSame(
            'database',
            config('queue.default')
        );

        self::assertFalse(
            config(
                'queue.connections.database.after_commit'
            )
        );

        $queueDatabaseConnection =
            config(
                'queue.connections.database.connection'
            );

        self::assertTrue(
            $queueDatabaseConnection === null
            || $queueDatabaseConnection
                === $this->db->getName(),
            'La cola database debe usar la misma conexión PostgreSQL que la operación.'
        );

        $batch = $this->createReadyBatch(
            validRows: 1
        );

        $jobsBefore =
            $this->db
                ->table('system.jobs')
                ->count();

        try {
            $this->db->transaction(
                function () use (
                    $batch,
                    $jobsBefore
                ): void {
                    $confirmed =
                        $this->app->make(
                            ConfirmPersonImportBatch::class
                        )->execute(
                            $batch->id
                        );

                    self::assertSame(
                        ImportBatchStatus::QUEUED,
                        $confirmed->status
                    );

                    self::assertSame(
                        $jobsBefore + 1,
                        $this->db
                            ->table('system.jobs')
                            ->count()
                    );

                    throw new RuntimeException(
                        'ROLLBACK CONTROLADO POST ENCOLADO'
                    );
                }
            );
        } catch (RuntimeException $error) {
            self::assertSame(
                'ROLLBACK CONTROLADO POST ENCOLADO',
                $error->getMessage()
            );

            $batch->refresh();

            self::assertSame(
                ImportBatchStatus::READY,
                $batch->status
            );

            self::assertNull(
                $batch->confirmed_at
            );

            self::assertSame(
                $jobsBefore,
                $this->db
                    ->table('system.jobs')
                    ->count()
            );

            return;
        }

        self::fail(
            'La transacción de prueba debía revertirse después de encolar.'
        );
    }

    private function createReadyBatch(
        int $validRows,
        int $invalidRows = 0,
        string $importType = 'PERSONAS'
    ): ImportBatch {
        $userId = (int) $this->db
            ->table('system.users')
            ->insertGetId([
                'name' => 'USUARIO CONFIRMACION IMPORTACION',
                'email' => Str::uuid()
                    .'@confirm-import.siga.test',
                'password' => Hash::make(
                    'ClaveSegura123!'
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return ImportBatch::query()->create([
            'uuid' => (string) Str::uuid(),
            'import_type' => $importType,
            'status' => ImportBatchStatus::READY,
            'original_filename' => 'personas.xlsx',
            'storage_disk' => 'local',
            'storage_path' => 'imports/personas/confirm',
            'file_sha256' => hash(
                'sha256',
                Str::uuid()->toString()
            ),
            'options' => [
                'template_version' => '2',
            ],
            'total_rows' => $validRows + $invalidRows,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'processed_rows' => 0,
            'failed_rows' => 0,
            'created_by' => $userId,
        ]);
    }
}

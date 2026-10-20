<?php

namespace Tests\Functional\Importing;

use App\Actions\Importing\Personas\ProcessPersonImportBatchChunk;
use App\Actions\Importing\TransitionImportBatch;
use App\Importing\ImportBatchStatus;
use App\Importing\ImportRowStatus;
use App\Jobs\Importing\Personas\ProcessPersonImportBatchJob;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Functional\FunctionalTestCase;

class ProcessPersonImportBatchJobTest extends FunctionalTestCase
{
    public function test_job_exposes_bounded_retry_and_overlap_policy(): void
    {
        $job =
            new ProcessPersonImportBatchJob(
                123
            );

        self::assertSame(
            5,
            $job->tries
        );

        self::assertSame(
            60,
            $job->timeout
        );

        self::assertSame(
            [
                5,
                15,
                30,
                60,
            ],
            $job->backoff()
        );

        $middleware =
            $job->middleware();

        self::assertCount(
            1,
            $middleware
        );

        self::assertInstanceOf(
            WithoutOverlapping::class,
            $middleware[0]
        );

        self::assertSame(
            'database',
            $job->connection
        );

        self::assertSame(
            'person-import-batch:123',
            $middleware[0]->key
        );

        self::assertNull(
            $middleware[0]->releaseAfter
        );

        self::assertSame(
            80,
            $middleware[0]->expiresAfter
        );
    }

    public function test_job_dispatches_next_chunk_while_batch_remains_processing(): void
    {
        Queue::fake();

        $batch =
            $this->createQueuedBatch(
                validRows: 101
            );

        for ($index = 0; $index < 101; $index++) {
            $this->createValidRow(
                $batch,
                $index + 2,
                'PERSONA JOB '
                    .str_pad(
                        (string) $index,
                        3,
                        '0',
                        STR_PAD_LEFT
                    )
            );
        }

        $job =
            new ProcessPersonImportBatchJob(
                $batch->id
            );

        $job->handle(
            $this->app->make(
                ProcessPersonImportBatchChunk::class
            )
        );

        $batch->refresh();

        self::assertSame(
            ImportBatchStatus::PROCESSING,
            $batch->status
        );

        self::assertSame(
            100,
            $batch->processed_rows
        );

        self::assertSame(
            1,
            ImportRow::query()
                ->where(
                    'import_batch_id',
                    $batch->id
                )
                ->where(
                    'status',
                    ImportRowStatus::VALID
                )
                ->count()
        );

        Queue::assertPushed(
            ProcessPersonImportBatchJob::class,
            static fn (
                ProcessPersonImportBatchJob $queued
            ): bool => $queued->batchId
                === $batch->id
                && $queued->connection === 'database'
        );

        Queue::fake();

        $secondJob =
            new ProcessPersonImportBatchJob(
                $batch->id
            );

        $secondJob->handle(
            $this->app->make(
                ProcessPersonImportBatchChunk::class
            )
        );

        $batch->refresh();

        self::assertSame(
            ImportBatchStatus::COMPLETED,
            $batch->status
        );

        self::assertSame(
            101,
            $batch->processed_rows
        );

        self::assertSame(
            0,
            $batch->failed_rows
        );

        Queue::assertNothingPushed();
    }

    public function test_failed_job_marks_processing_batch_failed(): void
    {
        $batch =
            $this->createQueuedBatch(
                validRows: 1
            );

        $batch =
            (new TransitionImportBatch)
                ->execute(
                    $batch->id,
                    ImportBatchStatus::PROCESSING
                );

        self::assertSame(
            ImportBatchStatus::PROCESSING,
            $batch->status
        );

        $job =
            new ProcessPersonImportBatchJob(
                $batch->id
            );

        $job->failed(
            new RuntimeException(
                'FALLO TECNICO AGOTADO'
            )
        );

        $batch->refresh();

        self::assertSame(
            ImportBatchStatus::FAILED,
            $batch->status
        );

        self::assertNotNull(
            $batch->finished_at
        );
    }

    private function createQueuedBatch(
        int $validRows
    ): ImportBatch {
        $userId = (int) $this->db
            ->table('system.users')
            ->insertGetId([
                'name' => 'USUARIO JOB IMPORTACION',
                'email' => Str::uuid()
                    .'@process-job.siga.test',
                'password' => Hash::make(
                    'ClaveSegura123!'
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $batch =
            ImportBatch::query()->create([
                'uuid' => (string) Str::uuid(),
                'import_type' => 'PERSONAS',
                'status' => ImportBatchStatus::READY,
                'original_filename' => 'personas.xlsx',
                'storage_disk' => 'local',
                'storage_path' => 'imports/personas/job',
                'file_sha256' => hash(
                    'sha256',
                    Str::uuid()->toString()
                ),
                'options' => [
                    'template_version' => '2',
                ],
                'total_rows' => $validRows,
                'valid_rows' => $validRows,
                'invalid_rows' => 0,
                'created_by' => $userId,
            ]);

        return (new TransitionImportBatch)
            ->execute(
                $batch->id,
                ImportBatchStatus::QUEUED
            );
    }

    private function createValidRow(
        ImportBatch $batch,
        int $rowNumber,
        string $name
    ): ImportRow {
        return ImportRow::query()->create([
            'import_batch_id' => $batch->id,
            'row_number' => $rowNumber,
            'status' => ImportRowStatus::VALID,
            'raw_data' => [
                'nombres' => $name,
            ],
            'normalized_data' => [
                'nombres' => $name,
                'apellido_paterno' => 'PRUEBA',
                'apellido_materno' => null,
                'curp' => null,
                'rfc' => null,
                'fecha_nacimiento' => null,
                'sexo' => null,
                'estado_civil' => null,
                'correo_institucional' => null,
                'correo_personal' => null,
                'pais_origen_alpha3' => null,
                'pais_nacimiento_alpha3' => null,
                'territorio_nacimiento_clave' => null,
                'id_pais_origen' => null,
                'id_pais_nacimiento' => null,
                'id_territorio_nacimiento' => null,
            ],
            'validation_errors' => [],
            'result_data' => null,
        ]);
    }
}

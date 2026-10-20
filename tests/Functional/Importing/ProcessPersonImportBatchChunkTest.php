<?php

namespace Tests\Functional\Importing;

use App\Actions\Importing\Personas\ProcessPersonImportBatchChunk;
use App\Actions\Importing\TransitionImportBatch;
use App\Importing\ImportBatchStatus;
use App\Importing\ImportRowStatus;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Functional\FunctionalTestCase;

class ProcessPersonImportBatchChunkTest extends FunctionalTestCase
{
    public function test_processes_valid_rows_and_completes_batch(): void
    {
        $batch = $this->createQueuedBatch(
            validRows: 2
        );

        $this->createRow(
            $batch,
            2,
            ImportRowStatus::VALID,
            $this->normalizedRow([
                'nombres' => 'PRIMERA IMPORTADA',
            ])
        );

        $this->createRow(
            $batch,
            3,
            ImportRowStatus::VALID,
            $this->normalizedRow([
                'nombres' => 'SEGUNDA IMPORTADA',
            ])
        );

        $processed =
            $this->app->make(
                ProcessPersonImportBatchChunk::class
            )->execute(
                $batch->id
            );

        self::assertSame(
            ImportBatchStatus::COMPLETED,
            $processed->status
        );

        self::assertSame(
            2,
            $processed->processed_rows
        );

        self::assertSame(
            0,
            $processed->failed_rows
        );

        self::assertNotNull(
            $processed->confirmed_at
        );

        self::assertNotNull(
            $processed->started_at
        );

        self::assertNotNull(
            $processed->finished_at
        );

        $rows = ImportRow::query()
            ->where(
                'import_batch_id',
                $batch->id
            )
            ->orderBy('row_number')
            ->get();

        self::assertCount(
            2,
            $rows
        );

        foreach ($rows as $row) {
            self::assertSame(
                ImportRowStatus::PROCESSED,
                $row->status
            );

            self::assertArrayHasKey(
                'id_persona',
                $row->result_data
            );

            self::assertArrayHasKey(
                'num_expediente',
                $row->result_data
            );
        }

        self::assertSame(
            2,
            $this->db
                ->table('institutional.persons')
                ->whereIn(
                    'nombres',
                    [
                        'PRIMERA IMPORTADA',
                        'SEGUNDA IMPORTADA',
                    ]
                )
                ->count()
        );

        self::assertSame(
            2,
            $this->db
                ->table('system.activities')
                ->where(
                    'id_usuario',
                    $batch->created_by
                )
                ->where(
                    'entidad',
                    'PERSONA'
                )
                ->where(
                    'accion',
                    'CREACION'
                )
                ->count()
        );
    }

    #[DataProvider('uniqueRaceFields')]
    public function test_known_uniqueness_race_fails_only_affected_row_and_continues(
        string $field,
        string $value
    ): void {
        $this->db
            ->table('institutional.persons')
            ->insert([
                'nombres' => 'PERSONA CREADA DESPUES DEL PREVIEW',
                'apellido_paterno' => 'EXISTENTE',
                $field => $value,
            ]);

        $batch = $this->createQueuedBatch(
            validRows: 2
        );

        $this->createRow(
            $batch,
            2,
            ImportRowStatus::VALID,
            $this->normalizedRow([
                'nombres' => 'CONFLICTO CONCURRENTE',
                $field => $value,
            ])
        );

        $this->createRow(
            $batch,
            3,
            ImportRowStatus::VALID,
            $this->normalizedRow([
                'nombres' => 'IMPORTACION CONTINUA',
            ])
        );

        $processed =
            $this->app->make(
                ProcessPersonImportBatchChunk::class
            )->execute(
                $batch->id
            );

        self::assertSame(
            ImportBatchStatus::COMPLETED,
            $processed->status
        );

        self::assertSame(
            1,
            $processed->processed_rows
        );

        self::assertSame(
            1,
            $processed->failed_rows
        );

        $rows = ImportRow::query()
            ->where(
                'import_batch_id',
                $batch->id
            )
            ->orderBy('row_number')
            ->get();

        self::assertSame(
            ImportRowStatus::FAILED,
            $rows[0]->status
        );

        self::assertSame(
            'PERSON_UNIQUENESS_CONFLICT',
            $rows[0]->result_data['error_code']
        );

        self::assertSame(
            $field,
            $rows[0]->result_data['field']
        );

        self::assertSame(
            ImportRowStatus::PROCESSED,
            $rows[1]->status
        );

        self::assertArrayHasKey(
            'id_persona',
            $rows[1]->result_data
        );

        self::assertFalse(
            $this->db
                ->table('institutional.persons')
                ->where(
                    'nombres',
                    'CONFLICTO CONCURRENTE'
                )
                ->exists()
        );

        self::assertTrue(
            $this->db
                ->table('institutional.persons')
                ->where(
                    'nombres',
                    'IMPORTACION CONTINUA'
                )
                ->exists()
        );
    }

    public static function uniqueRaceFields(): iterable
    {
        yield 'CURP' => [
            'curp',
            'GARM900101HNTRRR01',
        ];

        yield 'RFC' => [
            'rfc',
            'GARM900101ABC',
        ];

        yield 'correo institucional' => [
            'correo_institucional',
            'concurrente@siga.test',
        ];
    }

    public function test_technical_failure_keeps_row_retryable_and_rethrows_error(): void
    {
        $batch = $this->createQueuedBatch(
            validRows: 1
        );

        $this->createRow(
            $batch,
            2,
            ImportRowStatus::VALID,
            $this->normalizedRow([
                'nombres' => 'FALLO TECNICO IMPORTACION',
                'id_pais_origen' => (string) Str::uuid(),
            ])
        );

        try {
            $this->app->make(
                ProcessPersonImportBatchChunk::class
            )->execute(
                $batch->id
            );
        } catch (QueryException $error) {
            self::assertSame(
                '23503',
                $error->getCode()
            );

            $batch->refresh();

            self::assertSame(
                ImportBatchStatus::PROCESSING,
                $batch->status
            );

            self::assertNotNull(
                $batch->started_at
            );

            self::assertNull(
                $batch->finished_at
            );

            self::assertSame(
                0,
                $batch->processed_rows
            );

            self::assertSame(
                0,
                $batch->failed_rows
            );

            $row = ImportRow::query()
                ->where(
                    'import_batch_id',
                    $batch->id
                )
                ->firstOrFail();

            self::assertSame(
                ImportRowStatus::VALID,
                $row->status
            );

            self::assertNull(
                $row->result_data
            );

            self::assertFalse(
                $this->db
                    ->table('institutional.persons')
                    ->where(
                        'nombres',
                        'FALLO TECNICO IMPORTACION'
                    )
                    ->exists()
            );

            return;
        }

        self::fail(
            'El fallo técnico debe propagarse para permitir reintento del Job.'
        );
    }

    public function test_invalid_preview_rows_are_not_processed(): void
    {
        $batch = $this->createQueuedBatch(
            validRows: 1,
            invalidRows: 1
        );

        $this->createRow(
            $batch,
            2,
            ImportRowStatus::VALID,
            $this->normalizedRow([
                'nombres' => 'VALIDA PARA PROCESAR',
            ])
        );

        $this->createRow(
            $batch,
            3,
            ImportRowStatus::INVALID,
            $this->normalizedRow([
                'nombres' => 'INVALIDA DE PREVIEW',
            ]),
            [
                'curp' => [
                    'CURP inválida de prueba.',
                ],
            ]
        );

        $processed =
            $this->app->make(
                ProcessPersonImportBatchChunk::class
            )->execute(
                $batch->id
            );

        self::assertSame(
            ImportBatchStatus::COMPLETED,
            $processed->status
        );

        self::assertSame(
            1,
            $processed->processed_rows
        );

        self::assertSame(
            0,
            $processed->failed_rows
        );

        $rows = ImportRow::query()
            ->where(
                'import_batch_id',
                $batch->id
            )
            ->orderBy('row_number')
            ->get();

        self::assertSame(
            ImportRowStatus::PROCESSED,
            $rows[0]->status
        );

        self::assertSame(
            ImportRowStatus::INVALID,
            $rows[1]->status
        );

        self::assertNull(
            $rows[1]->result_data
        );

        self::assertFalse(
            $this->db
                ->table('institutional.persons')
                ->where(
                    'nombres',
                    'INVALIDA DE PREVIEW'
                )
                ->exists()
        );
    }

    public function test_rejects_processing_for_other_import_type(): void
    {
        $batch = $this->createQueuedBatch(
            validRows: 1,
            importType: 'OTRO'
        );

        $this->createRow(
            $batch,
            2,
            ImportRowStatus::VALID,
            $this->normalizedRow()
        );

        try {
            $this->app->make(
                ProcessPersonImportBatchChunk::class
            )->execute(
                $batch->id
            );
        } catch (DomainException) {
            $batch->refresh();

            self::assertSame(
                ImportBatchStatus::QUEUED,
                $batch->status
            );

            $row = ImportRow::query()
                ->where(
                    'import_batch_id',
                    $batch->id
                )
                ->firstOrFail();

            self::assertSame(
                ImportRowStatus::VALID,
                $row->status
            );

            return;
        }

        self::fail(
            'Un procesador PERSONAS no debe aceptar otro tipo de importación.'
        );
    }

    public function test_processes_one_bounded_chunk_and_resumes_without_duplicates(): void
    {
        $batch = $this->createQueuedBatch(
            validRows: 101
        );

        for ($index = 0; $index < 101; $index++) {
            $this->createRow(
                $batch,
                $index + 2,
                ImportRowStatus::VALID,
                $this->normalizedRow([
                    'nombres' => 'PERSONA CHUNK '
                        .str_pad(
                            (string) $index,
                            3,
                            '0',
                            STR_PAD_LEFT
                        ),
                ])
            );
        }

        $firstPass =
            $this->app->make(
                ProcessPersonImportBatchChunk::class
            )->execute(
                $batch->id
            );

        self::assertSame(
            ImportBatchStatus::PROCESSING,
            $firstPass->status
        );

        self::assertSame(
            100,
            $firstPass->processed_rows
        );

        self::assertSame(
            0,
            $firstPass->failed_rows
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

        self::assertSame(
            100,
            $this->db
                ->table('institutional.persons')
                ->where(
                    'nombres',
                    'like',
                    'PERSONA CHUNK %'
                )
                ->count()
        );

        $secondPass =
            $this->app->make(
                ProcessPersonImportBatchChunk::class
            )->execute(
                $batch->id
            );

        self::assertSame(
            ImportBatchStatus::COMPLETED,
            $secondPass->status
        );

        self::assertSame(
            101,
            $secondPass->processed_rows
        );

        self::assertSame(
            0,
            $secondPass->failed_rows
        );

        self::assertSame(
            101,
            $this->db
                ->table('institutional.persons')
                ->where(
                    'nombres',
                    'like',
                    'PERSONA CHUNK %'
                )
                ->count()
        );

        $thirdPass =
            $this->app->make(
                ProcessPersonImportBatchChunk::class
            )->execute(
                $batch->id
            );

        self::assertSame(
            ImportBatchStatus::COMPLETED,
            $thirdPass->status
        );

        self::assertSame(
            101,
            $thirdPass->processed_rows
        );

        self::assertSame(
            101,
            $this->db
                ->table('institutional.persons')
                ->where(
                    'nombres',
                    'like',
                    'PERSONA CHUNK %'
                )
                ->count()
        );
    }

    private function createQueuedBatch(
        int $validRows,
        int $invalidRows = 0,
        string $importType = 'PERSONAS'
    ): ImportBatch {
        $userId = (int) $this->db
            ->table('system.users')
            ->insertGetId([
                'name' => 'USUARIO PROCESAMIENTO IMPORTACION',
                'email' => Str::uuid()
                    .'@process-import.siga.test',
                'password' => Hash::make(
                    'ClaveSegura123!'
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $batch = ImportBatch::query()->create([
            'uuid' => (string) Str::uuid(),
            'import_type' => $importType,
            'status' => ImportBatchStatus::READY,
            'original_filename' => 'personas.xlsx',
            'storage_disk' => 'local',
            'storage_path' => 'imports/personas/process',
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
            'created_by' => $userId,
        ]);

        return (new TransitionImportBatch)
            ->execute(
                $batch->id,
                ImportBatchStatus::QUEUED
            );
    }

    /**
     * @param  array<string, mixed>  $normalizedData
     * @param  array<string, list<string>>  $validationErrors
     */
    private function createRow(
        ImportBatch $batch,
        int $rowNumber,
        ImportRowStatus $status,
        array $normalizedData,
        array $validationErrors = []
    ): ImportRow {
        return ImportRow::query()->create([
            'import_batch_id' => $batch->id,
            'row_number' => $rowNumber,
            'status' => $status,
            'raw_data' => [
                'nombres' => $normalizedData['nombres'],
            ],
            'normalized_data' => $normalizedData,
            'validation_errors' => $validationErrors,
            'result_data' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function normalizedRow(
        array $overrides = []
    ): array {
        $row = [
            'nombres' => 'PERSONA PROCESABLE',
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
        ];

        foreach ($overrides as $field => $value) {
            $row[$field] = $value;
        }

        return $row;
    }
}

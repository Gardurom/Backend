<?php

namespace Tests\Functional\Importing;

use App\Actions\Importing\Personas\ValidatePersonImportBatch;
use App\Actions\Importing\StageImportRows;
use App\Importing\ImportBatchStatus;
use App\Importing\ImportRowStatus;
use App\Importing\ImportSourceRow;
use App\Importing\InvalidImportBatchValidation;
use App\Importing\Personas\PersonImportReferenceLookup;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Functional\FunctionalTestCase;

class ValidatePersonImportBatchTest extends FunctionalTestCase
{
    public function test_validates_staged_rows_and_persists_preview_results(): void
    {
        $batch = $this->createBatch();

        (new StageImportRows)->execute(
            $batch->id,
            [
                new ImportSourceRow(
                    2,
                    $this->validRow([
                        'nombres' => '  MARIA  ',
                    ])
                ),
                new ImportSourceRow(
                    3,
                    $this->validRow([
                        'nombres' => 123,
                    ])
                ),
            ]
        );

        $validated =
            $this->app->make(
                ValidatePersonImportBatch::class
            )->execute(
                $batch->id
            );

        self::assertSame(
            ImportBatchStatus::READY,
            $validated->status
        );

        self::assertSame(
            2,
            $validated->total_rows
        );

        self::assertSame(
            1,
            $validated->valid_rows
        );

        self::assertSame(
            1,
            $validated->invalid_rows
        );

        self::assertSame(
            0,
            $validated->processed_rows
        );

        self::assertSame(
            0,
            $validated->failed_rows
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

        self::assertSame(
            ImportRowStatus::VALID,
            $rows[0]->status
        );

        self::assertSame(
            'MARIA',
            $rows[0]->normalized_data['nombres']
        );

        self::assertSame(
            [],
            $rows[0]->validation_errors
        );

        self::assertSame(
            ImportRowStatus::INVALID,
            $rows[1]->status
        );

        self::assertSame(
            123,
            $rows[1]->normalized_data['nombres']
        );

        self::assertArrayHasKey(
            'nombres',
            $rows[1]->validation_errors
        );
    }

    public function test_invalid_rows_do_not_fail_validation_batch(): void
    {
        $batch = $this->createBatch();

        (new StageImportRows)->execute(
            $batch->id,
            [
                new ImportSourceRow(
                    2,
                    $this->validRow([
                        'apellido_paterno' => null,
                        'apellido_materno' => null,
                    ])
                ),
            ]
        );

        $validated =
            $this->app->make(
                ValidatePersonImportBatch::class
            )->execute(
                $batch->id
            );

        self::assertSame(
            ImportBatchStatus::READY,
            $validated->status
        );

        self::assertSame(
            0,
            $validated->valid_rows
        );

        self::assertSame(
            1,
            $validated->invalid_rows
        );

        $row = ImportRow::query()
            ->where(
                'import_batch_id',
                $batch->id
            )
            ->firstOrFail();

        self::assertSame(
            ImportRowStatus::INVALID,
            $row->status
        );

        self::assertNotSame(
            [],
            $row->validation_errors
        );
    }

    public function test_rejects_person_validation_for_other_import_type(): void
    {
        $batch = $this->createBatch(
            importType: 'OTRO_TIPO'
        );

        (new StageImportRows)->execute(
            $batch->id,
            [
                new ImportSourceRow(
                    2,
                    $this->validRow()
                ),
            ]
        );

        try {
            $this->app->make(
                ValidatePersonImportBatch::class
            )->execute(
                $batch->id
            );
        } catch (InvalidImportBatchValidation) {
            $batch->refresh();

            self::assertSame(
                ImportBatchStatus::UPLOADED,
                $batch->status
            );

            self::assertSame(
                ImportRowStatus::PENDING,
                $batch
                    ->rows()
                    ->firstOrFail()
                    ->status
            );

            return;
        }

        self::fail(
            'Un lote que no sea PERSONAS debe rechazarse.'
        );
    }

    public function test_rejects_batch_that_is_not_uploaded(): void
    {
        $batch = $this->createBatch();

        (new StageImportRows)->execute(
            $batch->id,
            [
                new ImportSourceRow(
                    2,
                    $this->validRow()
                ),
            ]
        );

        $batch->status =
            ImportBatchStatus::VALIDATING;

        $batch->save();

        try {
            $this->app->make(
                ValidatePersonImportBatch::class
            )->execute(
                $batch->id
            );
        } catch (InvalidImportBatchValidation) {
            $batch->refresh();

            self::assertSame(
                ImportBatchStatus::VALIDATING,
                $batch->status
            );

            self::assertSame(
                ImportRowStatus::PENDING,
                $batch
                    ->rows()
                    ->firstOrFail()
                    ->status
            );

            return;
        }

        self::fail(
            'Solo un lote UPLOADED debe iniciar validación.'
        );
    }

    public function test_technical_failure_marks_batch_failed_and_rethrows_error(): void
    {
        $batch = $this->createBatch();

        (new StageImportRows)->execute(
            $batch->id,
            [
                new ImportSourceRow(
                    2,
                    $this->validRow([
                        'pais_origen_alpha3' => 'MEX',
                    ])
                ),
            ]
        );

        $this->app->instance(
            PersonImportReferenceLookup::class,
            new FailingPersonImportReferenceLookup
        );

        try {
            $this->app->make(
                ValidatePersonImportBatch::class
            )->execute(
                $batch->id
            );
        } catch (RuntimeException $error) {
            self::assertSame(
                'FALLO TECNICO CONTROLADO',
                $error->getMessage()
            );

            $batch->refresh();

            self::assertSame(
                ImportBatchStatus::FAILED,
                $batch->status
            );

            self::assertNotNull(
                $batch->finished_at
            );

            self::assertNull(
                $batch->confirmed_at
            );

            self::assertNull(
                $batch->started_at
            );

            $row = ImportRow::query()
                ->where(
                    'import_batch_id',
                    $batch->id
                )
                ->firstOrFail();

            self::assertSame(
                ImportRowStatus::PENDING,
                $row->status
            );

            self::assertNull(
                $row->normalized_data
            );

            self::assertNull(
                $row->validation_errors
            );

            return;
        }

        self::fail(
            'El fallo técnico debe volver a propagarse después de marcar el lote FAILED.'
        );
    }

    public function test_rejects_reordered_source_columns_after_staging(): void
    {
        $batch = $this->createBatch();

        $row = $this->validRow();

        $reordered = [
            'apellido_paterno' => $row['apellido_paterno'],
            'nombres' => $row['nombres'],
        ];

        foreach ($row as $field => $value) {
            if (
                $field === 'nombres'
                || $field === 'apellido_paterno'
            ) {
                continue;
            }

            $reordered[$field] = $value;
        }

        (new StageImportRows)->execute(
            $batch->id,
            [
                new ImportSourceRow(
                    2,
                    $reordered
                ),
            ]
        );

        try {
            $this->app->make(
                ValidatePersonImportBatch::class
            )->execute(
                $batch->id
            );
        } catch (InvalidImportBatchValidation) {
            $batch->refresh();

            self::assertSame(
                ImportBatchStatus::UPLOADED,
                $batch->status
            );

            $row = $batch
                ->rows()
                ->firstOrFail();

            self::assertSame(
                ImportRowStatus::PENDING,
                $row->status
            );

            return;
        }

        self::fail(
            'La importacion PERSONAS debe rechazar columnas fuente reordenadas.'
        );
    }

    private function createBatch(
        string $importType = 'PERSONAS'
    ): ImportBatch {
        $userId = (int) $this->db
            ->table('system.users')
            ->insertGetId([
                'name' => 'USUARIO VALIDACION IMPORTACION',
                'email' => Str::uuid()
                    .'@validate-import.siga.test',
                'password' => Hash::make(
                    'ClaveSegura123!'
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return ImportBatch::query()->create([
            'uuid' => (string) Str::uuid(),
            'import_type' => $importType,
            'original_filename' => 'personas.xlsx',
            'storage_disk' => 'local',
            'storage_path' => 'imports/personas/validation',
            'file_sha256' => hash(
                'sha256',
                Str::uuid()->toString()
            ),
            'created_by' => $userId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validRow(
        array $overrides = []
    ): array {
        $row = [
            'nombres' => 'PERSONA',
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
        ];

        foreach ($overrides as $field => $value) {
            $row[$field] = $value;
        }

        return $row;
    }
}

final class FailingPersonImportReferenceLookup implements PersonImportReferenceLookup
{
    public function findActiveCountryIdByAlpha3(
        string $alpha3
    ): ?string {
        throw new RuntimeException(
            'FALLO TECNICO CONTROLADO'
        );
    }

    public function findActiveTerritoryIds(
        string $countryId,
        string $officialKey
    ): array {
        throw new RuntimeException(
            'FALLO TECNICO CONTROLADO'
        );
    }
}

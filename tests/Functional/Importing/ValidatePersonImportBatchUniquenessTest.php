<?php

namespace Tests\Functional\Importing;

use App\Actions\Importing\Personas\ValidatePersonImportBatch;
use App\Actions\Importing\StageImportRows;
use App\Importing\ImportBatchStatus;
use App\Importing\ImportRowStatus;
use App\Importing\ImportSourceRow;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Functional\FunctionalTestCase;

class ValidatePersonImportBatchUniquenessTest extends FunctionalTestCase
{
    #[DataProvider('uniqueFields')]
    public function test_existing_person_value_marks_import_row_invalid(
        string $field,
        string $value
    ): void {
        $this->createExistingPerson([
            $field => $value,
        ]);

        $batch = $this->createBatch();

        (new StageImportRows)->execute(
            $batch->id,
            [
                new ImportSourceRow(
                    2,
                    $this->validRow([
                        $field => $value,
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

        self::assertArrayHasKey(
            $field,
            $row->validation_errors
        );
    }

    #[DataProvider('uniqueFields')]
    public function test_duplicate_value_inside_same_file_invalidates_all_occurrences(
        string $field,
        string $value
    ): void {
        $batch = $this->createBatch();

        (new StageImportRows)->execute(
            $batch->id,
            [
                new ImportSourceRow(
                    2,
                    $this->validRow([
                        'nombres' => 'PRIMERA',
                        $field => $value,
                    ])
                ),
                new ImportSourceRow(
                    3,
                    $this->validRow([
                        'nombres' => 'SEGUNDA',
                        $field => $value,
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
            2,
            $validated->invalid_rows
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
                ImportRowStatus::INVALID,
                $row->status
            );

            self::assertArrayHasKey(
                $field,
                $row->validation_errors
            );
        }
    }

    public function test_null_unique_values_are_not_considered_duplicates(): void
    {
        $batch = $this->createBatch();

        (new StageImportRows)->execute(
            $batch->id,
            [
                new ImportSourceRow(
                    2,
                    $this->validRow([
                        'nombres' => 'PRIMERA',
                    ])
                ),
                new ImportSourceRow(
                    3,
                    $this->validRow([
                        'nombres' => 'SEGUNDA',
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
            2,
            $validated->valid_rows
        );

        self::assertSame(
            0,
            $validated->invalid_rows
        );
    }

    public function test_personal_email_is_not_unique_for_import(): void
    {
        $batch = $this->createBatch();

        $email = 'compartido@example.invalid';

        (new StageImportRows)->execute(
            $batch->id,
            [
                new ImportSourceRow(
                    2,
                    $this->validRow([
                        'nombres' => 'PRIMERA',
                        'correo_personal' => $email,
                    ])
                ),
                new ImportSourceRow(
                    3,
                    $this->validRow([
                        'nombres' => 'SEGUNDA',
                        'correo_personal' => $email,
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
            2,
            $validated->valid_rows
        );

        self::assertSame(
            0,
            $validated->invalid_rows
        );
    }

    public static function uniqueFields(): iterable
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
            'persona.unica@siga.test',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createExistingPerson(
        array $attributes
    ): void {
        $this->db
            ->table('institutional.persons')
            ->insert([
                ...[
                    'nombres' => 'PERSONA EXISTENTE',
                    'apellido_paterno' => 'PRUEBA',
                ],
                ...$attributes,
            ]);
    }

    public function test_duplicate_unique_value_is_reported_even_when_one_row_has_other_errors(): void
    {
        $batch = $this->createBatch();

        $curp = 'GARM900101HNTRRR01';

        (new StageImportRows)->execute(
            $batch->id,
            [
                new ImportSourceRow(
                    2,
                    $this->validRow([
                        'nombres' => 123,
                        'curp' => $curp,
                    ])
                ),
                new ImportSourceRow(
                    3,
                    $this->validRow([
                        'nombres' => 'SEGUNDA',
                        'curp' => $curp,
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
            0,
            $validated->valid_rows
        );

        self::assertSame(
            2,
            $validated->invalid_rows
        );

        $rows = ImportRow::query()
            ->where(
                'import_batch_id',
                $batch->id
            )
            ->orderBy('row_number')
            ->get();

        self::assertArrayHasKey(
            'nombres',
            $rows[0]->validation_errors
        );

        self::assertArrayHasKey(
            'curp',
            $rows[0]->validation_errors
        );

        self::assertArrayHasKey(
            'curp',
            $rows[1]->validation_errors
        );
    }

    private function createBatch(): ImportBatch
    {
        $userId = (int) $this->db
            ->table('system.users')
            ->insertGetId([
                'name' => 'USUARIO UNICIDAD IMPORTACION',
                'email' => Str::uuid()
                    .'@uniqueness-import.siga.test',
                'password' => Hash::make(
                    'ClaveSegura123!'
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return ImportBatch::query()->create([
            'uuid' => (string) Str::uuid(),
            'import_type' => 'PERSONAS',
            'original_filename' => 'personas.xlsx',
            'storage_disk' => 'local',
            'storage_path' => 'imports/personas/uniqueness',
            'file_sha256' => hash(
                'sha256',
                Str::uuid()->toString()
            ),
            'options' => [
                'template_version' => '2',
            ],
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

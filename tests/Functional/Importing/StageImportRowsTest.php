<?php

namespace Tests\Functional\Importing;

use App\Actions\Importing\StageImportRows;
use App\Actions\Importing\TransitionImportBatch;
use App\Importing\ImportBatchStatus;
use App\Importing\ImportRowStatus;
use App\Importing\ImportSourceRow;
use App\Importing\InvalidImportBatchStaging;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Functional\FunctionalTestCase;

class StageImportRowsTest extends FunctionalTestCase
{
    public function test_source_row_preserves_number_and_raw_data(): void
    {
        $row = new ImportSourceRow(
            7,
            [
                'nombres' => 'MARIA',
                'apellido_paterno' => 'LOPEZ',
            ]
        );

        self::assertSame(
            7,
            $row->rowNumber
        );

        self::assertSame(
            [
                'nombres' => 'MARIA',
                'apellido_paterno' => 'LOPEZ',
            ],
            $row->rawData
        );
    }

    public function test_source_row_rejects_non_positive_number(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new ImportSourceRow(
            0,
            [
                'nombres' => 'INVALIDA',
            ]
        );
    }

    public function test_stages_rows_preserving_source_numbers_and_updates_total(): void
    {
        $batch = $this->createBatch();

        $staged = (new StageImportRows)->execute(
            $batch->id,
            [
                new ImportSourceRow(
                    2,
                    [
                        'nombres' => 'MARIA',
                        'apellido_paterno' => 'LOPEZ',
                    ]
                ),
                new ImportSourceRow(
                    5,
                    [
                        'nombres' => 'JUAN',
                        'apellido_paterno' => 'PEREZ',
                    ]
                ),
            ]
        );

        self::assertSame(
            ImportBatchStatus::UPLOADED,
            $staged->status
        );

        self::assertSame(
            2,
            $staged->total_rows
        );

        self::assertSame(
            0,
            $staged->valid_rows
        );

        self::assertSame(
            0,
            $staged->invalid_rows
        );

        self::assertSame(
            0,
            $staged->processed_rows
        );

        self::assertSame(
            0,
            $staged->failed_rows
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
            [
                2,
                5,
            ],
            $rows
                ->pluck('row_number')
                ->all()
        );

        self::assertSame(
            ImportRowStatus::PENDING,
            $rows[0]->status
        );

        self::assertSame(
            ImportRowStatus::PENDING,
            $rows[1]->status
        );

        self::assertEquals(
            [
                'nombres' => 'MARIA',
                'apellido_paterno' => 'LOPEZ',
            ],
            $rows[0]->raw_data
        );

        self::assertEquals(
            [
                'nombres' => 'JUAN',
                'apellido_paterno' => 'PEREZ',
            ],
            $rows[1]->raw_data
        );
    }

    public function test_staging_accepts_generator_source(): void
    {
        $batch = $this->createBatch();

        $source = (function (): iterable {
            yield new ImportSourceRow(
                3,
                [
                    'nombres' => 'PRIMERA',
                ]
            );

            yield new ImportSourceRow(
                8,
                [
                    'nombres' => 'SEGUNDA',
                ]
            );

            yield new ImportSourceRow(
                13,
                [
                    'nombres' => 'TERCERA',
                ]
            );
        })();

        $staged = (new StageImportRows)->execute(
            $batch->id,
            $source
        );

        self::assertSame(
            3,
            $staged->total_rows
        );

        self::assertSame(
            [
                3,
                8,
                13,
            ],
            ImportRow::query()
                ->where(
                    'import_batch_id',
                    $batch->id
                )
                ->orderBy('row_number')
                ->pluck('row_number')
                ->all()
        );
    }

    public function test_empty_source_is_rejected_without_changing_batch(): void
    {
        $batch = $this->createBatch();

        try {
            (new StageImportRows)->execute(
                $batch->id,
                []
            );
        } catch (InvalidImportBatchStaging) {
            $batch->refresh();

            self::assertSame(
                ImportBatchStatus::UPLOADED,
                $batch->status
            );

            self::assertSame(
                0,
                $batch->total_rows
            );

            self::assertSame(
                0,
                ImportRow::query()
                    ->where(
                        'import_batch_id',
                        $batch->id
                    )
                    ->count()
            );

            return;
        }

        self::fail(
            'Un lote sin filas fuente debe rechazarse.'
        );
    }

    public function test_batch_outside_uploaded_state_cannot_be_staged(): void
    {
        $batch = $this->createBatch();

        (new TransitionImportBatch)->execute(
            $batch->id,
            ImportBatchStatus::VALIDATING
        );

        try {
            (new StageImportRows)->execute(
                $batch->id,
                [
                    new ImportSourceRow(
                        2,
                        [
                            'nombres' => 'NO DEBE INSERTARSE',
                        ]
                    ),
                ]
            );
        } catch (InvalidImportBatchStaging) {
            $batch->refresh();

            self::assertSame(
                ImportBatchStatus::VALIDATING,
                $batch->status
            );

            self::assertSame(
                0,
                $batch->total_rows
            );

            self::assertSame(
                0,
                ImportRow::query()
                    ->where(
                        'import_batch_id',
                        $batch->id
                    )
                    ->count()
            );

            return;
        }

        self::fail(
            'Solo un lote UPLOADED puede entrar a staging.'
        );
    }

    public function test_batch_cannot_be_staged_twice(): void
    {
        $batch = $this->createBatch();
        $action = new StageImportRows;

        $action->execute(
            $batch->id,
            [
                new ImportSourceRow(
                    2,
                    [
                        'nombres' => 'ORIGINAL',
                    ]
                ),
            ]
        );

        try {
            $action->execute(
                $batch->id,
                [
                    new ImportSourceRow(
                        3,
                        [
                            'nombres' => 'SEGUNDA CARGA',
                        ]
                    ),
                ]
            );
        } catch (InvalidImportBatchStaging) {
            $batch->refresh();

            self::assertSame(
                1,
                $batch->total_rows
            );

            $rows = ImportRow::query()
                ->where(
                    'import_batch_id',
                    $batch->id
                )
                ->get();

            self::assertCount(
                1,
                $rows
            );

            self::assertSame(
                2,
                $rows->first()->row_number
            );

            self::assertEquals(
                [
                    'nombres' => 'ORIGINAL',
                ],
                $rows->first()->raw_data
            );

            return;
        }

        self::fail(
            'Un lote no debe permitir staging por segunda vez.'
        );
    }

    public function test_duplicate_source_row_numbers_roll_back_whole_staging(): void
    {
        $batch = $this->createBatch();

        try {
            (new StageImportRows)->execute(
                $batch->id,
                [
                    new ImportSourceRow(
                        2,
                        [
                            'nombres' => 'PRIMERA',
                        ]
                    ),
                    new ImportSourceRow(
                        2,
                        [
                            'nombres' => 'DUPLICADA',
                        ]
                    ),
                ]
            );
        } catch (QueryException $error) {
            self::assertSame(
                '23505',
                $error->getCode()
            );

            $batch->refresh();

            self::assertSame(
                0,
                $batch->total_rows
            );

            self::assertSame(
                0,
                ImportRow::query()
                    ->where(
                        'import_batch_id',
                        $batch->id
                    )
                    ->count()
            );

            return;
        }

        self::fail(
            'Los numeros de fila duplicados deben provocar rollback.'
        );
    }

    private function createBatch(): ImportBatch
    {
        $userId = (int) $this->db
            ->table('system.users')
            ->insertGetId([
                'name' => 'USUARIO STAGING IMPORTACION',
                'email' => Str::uuid()
                    .'@staging-import.siga.test',
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
            'storage_path' => 'imports/prueba/source',
            'file_sha256' => hash(
                'sha256',
                Str::uuid()->toString()
            ),
            'created_by' => $userId,
        ]);
    }
}

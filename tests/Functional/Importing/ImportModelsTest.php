<?php

namespace Tests\Functional\Importing;

use App\Importing\ImportBatchStatus;
use App\Importing\ImportRowStatus;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Functional\FunctionalTestCase;

class ImportModelsTest extends FunctionalTestCase
{
    public function test_import_status_enums_match_database_contract(): void
    {
        self::assertSame(
            [
                'UPLOADED',
                'VALIDATING',
                'READY',
                'QUEUED',
                'PROCESSING',
                'COMPLETED',
                'FAILED',
                'CANCELLED',
            ],
            array_map(
                static fn (ImportBatchStatus $status): string => $status->value,
                ImportBatchStatus::cases()
            )
        );

        self::assertSame(
            [
                'PENDING',
                'VALID',
                'INVALID',
                'PROCESSED',
                'FAILED',
                'SKIPPED',
            ],
            array_map(
                static fn (ImportRowStatus $status): string => $status->value,
                ImportRowStatus::cases()
            )
        );
    }

    public function test_import_models_use_expected_tables_and_relations(): void
    {
        $batch = new ImportBatch;

        self::assertSame(
            'system.import_batches',
            $batch->getTable()
        );

        $createdBy = $batch->createdBy();

        self::assertInstanceOf(
            BelongsTo::class,
            $createdBy
        );

        self::assertInstanceOf(
            User::class,
            $createdBy->getRelated()
        );

        self::assertSame(
            'created_by',
            $createdBy->getForeignKeyName()
        );

        self::assertSame(
            'id',
            $createdBy->getOwnerKeyName()
        );

        $rows = $batch->rows();

        self::assertInstanceOf(
            HasMany::class,
            $rows
        );

        self::assertInstanceOf(
            ImportRow::class,
            $rows->getRelated()
        );

        self::assertSame(
            'import_batch_id',
            $rows->getForeignKeyName()
        );

        self::assertSame(
            'id',
            $rows->getLocalKeyName()
        );

        $row = new ImportRow;

        self::assertSame(
            'system.import_rows',
            $row->getTable()
        );

        $rowBatch = $row->batch();

        self::assertInstanceOf(
            BelongsTo::class,
            $rowBatch
        );

        self::assertInstanceOf(
            ImportBatch::class,
            $rowBatch->getRelated()
        );

        self::assertSame(
            'import_batch_id',
            $rowBatch->getForeignKeyName()
        );

        self::assertSame(
            'id',
            $rowBatch->getOwnerKeyName()
        );
    }

    public function test_import_models_persist_defaults_casts_and_relations(): void
    {
        $userId = $this->createUser();

        $user = User::query()->findOrFail(
            $userId
        );

        $uuid = (string) Str::uuid();

        $options = [
            'template_version' => '1',
            'mode' => 'PERSON_ONLY',
        ];

        $batch = ImportBatch::query()->create([
            'uuid' => $uuid,
            'import_type' => 'PERSONAS',
            'original_filename' => 'personas.xlsx',
            'storage_disk' => 'local',
            'storage_path' => 'imports/personas.xlsx',
            'file_sha256' => hash(
                'sha256',
                'archivo-prueba-siga'
            ),
            'options' => $options,
            'created_by' => $userId,
        ]);

        $batch->refresh();

        self::assertSame(
            $uuid,
            $batch->uuid
        );

        self::assertSame(
            ImportBatchStatus::UPLOADED,
            $batch->status
        );

        self::assertEquals(
            $options,
            $batch->options
        );

        self::assertSame(
            0,
            $batch->total_rows
        );

        self::assertSame(
            0,
            $batch->valid_rows
        );

        self::assertSame(
            0,
            $batch->invalid_rows
        );

        self::assertSame(
            0,
            $batch->processed_rows
        );

        self::assertSame(
            0,
            $batch->failed_rows
        );

        self::assertInstanceOf(
            CarbonInterface::class,
            $batch->created_at
        );

        self::assertInstanceOf(
            CarbonInterface::class,
            $batch->updated_at
        );

        self::assertTrue(
            $batch->createdBy->is($user)
        );

        $rawData = [
            'nombres' => 'PERSONA IMPORTADA',
            'apellido_paterno' => 'PRUEBA',
        ];

        $row = ImportRow::query()->create([
            'import_batch_id' => $batch->id,
            'row_number' => 2,
            'raw_data' => $rawData,
        ]);

        $row->refresh();

        self::assertSame(
            ImportRowStatus::PENDING,
            $row->status
        );

        self::assertEquals(
            $rawData,
            $row->raw_data
        );

        self::assertNull(
            $row->normalized_data
        );

        self::assertNull(
            $row->validation_errors
        );

        self::assertNull(
            $row->result_data
        );

        self::assertTrue(
            $row->batch->is($batch)
        );

        self::assertTrue(
            $batch
                ->fresh()
                ->rows
                ->contains(
                    fn (ImportRow $item): bool => $item->is($row)
                )
        );
    }

    public function test_import_models_persist_enum_and_structured_updates(): void
    {
        $userId = $this->createUser();

        $batch = ImportBatch::query()->create([
            'uuid' => (string) Str::uuid(),
            'import_type' => 'PERSONAS',
            'original_filename' => 'personas.xlsx',
            'storage_disk' => 'local',
            'storage_path' => 'imports/personas.xlsx',
            'file_sha256' => hash(
                'sha256',
                'segunda-prueba-siga'
            ),
            'created_by' => $userId,
        ]);

        $row = ImportRow::query()->create([
            'import_batch_id' => $batch->id,
            'row_number' => 2,
            'raw_data' => [
                'nombres' => 'PERSONA',
            ],
        ]);

        $confirmedAt = now()->subMinutes(3);
        $startedAt = now()->subMinutes(2);
        $finishedAt = now()->subMinute();

        $batch->update([
            'status' => ImportBatchStatus::COMPLETED,
            'total_rows' => 1,
            'valid_rows' => 1,
            'processed_rows' => 1,
            'confirmed_at' => $confirmedAt,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
        ]);

        $resultData = [
            'person_id' => (string) Str::uuid(),
            'user_id' => $userId,
        ];

        $row->update([
            'status' => ImportRowStatus::PROCESSED,
            'normalized_data' => [
                'nombres' => 'PERSONA',
            ],
            'validation_errors' => [],
            'result_data' => $resultData,
        ]);

        $batch->refresh();
        $row->refresh();

        self::assertSame(
            ImportBatchStatus::COMPLETED,
            $batch->status
        );

        self::assertInstanceOf(
            CarbonInterface::class,
            $batch->confirmed_at
        );

        self::assertInstanceOf(
            CarbonInterface::class,
            $batch->started_at
        );

        self::assertInstanceOf(
            CarbonInterface::class,
            $batch->finished_at
        );

        self::assertSame(
            ImportRowStatus::PROCESSED,
            $row->status
        );

        self::assertSame(
            [
                'nombres' => 'PERSONA',
            ],
            $row->normalized_data
        );

        self::assertSame(
            [],
            $row->validation_errors
        );

        self::assertEquals(
            $resultData,
            $row->result_data
        );
    }

    private function createUser(): int
    {
        return (int) $this->db
            ->table('system.users')
            ->insertGetId([
                'name' => 'USUARIO IMPORTACIONES SIGA',
                'email' => Str::uuid()
                    .'@imports.siga.test',
                'password' => Hash::make(
                    'ClaveSegura123!'
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}

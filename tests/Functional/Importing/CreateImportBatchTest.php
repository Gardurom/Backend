<?php

namespace Tests\Functional\Importing;

use App\Actions\Importing\CreateImportBatch;
use App\Importing\ImportBatchStatus;
use App\Models\ImportBatch;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Functional\FunctionalTestCase;

class CreateImportBatchTest extends FunctionalTestCase
{
    public function test_creates_batch_and_stores_private_source_file(): void
    {
        Storage::fake('local');

        $userId = $this->createUser();

        $contents = "nombres,apellido_paterno\nMARIA,LOPEZ\n";

        $file = UploadedFile::fake()->createWithContent(
            'personas.xlsx',
            $contents
        );

        $options = [
            'template_version' => '1',
            'mode' => 'PERSON_ONLY',
        ];

        $batch = (new CreateImportBatch)->execute(
            $userId,
            'PERSONAS',
            $file,
            $options
        );

        self::assertInstanceOf(
            ImportBatch::class,
            $batch
        );

        self::assertSame(
            ImportBatchStatus::UPLOADED,
            $batch->status
        );

        self::assertTrue(
            Str::isUuid($batch->uuid)
        );

        self::assertSame(
            'PERSONAS',
            $batch->import_type
        );

        self::assertSame(
            'personas.xlsx',
            $batch->original_filename
        );

        self::assertSame(
            'local',
            $batch->storage_disk
        );

        self::assertSame(
            "imports/{$batch->uuid}/source",
            $batch->storage_path
        );

        self::assertStringNotContainsString(
            'personas.xlsx',
            $batch->storage_path
        );

        self::assertSame(
            hash(
                'sha256',
                $contents
            ),
            $batch->file_sha256
        );

        self::assertEquals(
            $options,
            $batch->options
        );

        self::assertSame(
            $userId,
            $batch->created_by
        );

        Storage::disk('local')->assertExists(
            $batch->storage_path
        );

        self::assertSame(
            $contents,
            Storage::disk('local')->get(
                $batch->storage_path
            )
        );

        self::assertDatabaseRecordMatches(
            $batch
        );
    }

    public function test_original_filename_is_metadata_not_storage_path(): void
    {
        Storage::fake('local');

        $userId = $this->createUser();

        $file = UploadedFile::fake()->createWithContent(
            'datos-personales.xlsx',
            'contenido'
        );

        $batch = (new CreateImportBatch)->execute(
            $userId,
            'PERSONAS',
            $file
        );

        self::assertSame(
            'datos-personales.xlsx',
            $batch->original_filename
        );

        self::assertSame(
            "imports/{$batch->uuid}/source",
            $batch->storage_path
        );

        self::assertStringNotContainsString(
            'datos-personales.xlsx',
            $batch->storage_path
        );
    }

    public function test_empty_options_are_persisted_as_null(): void
    {
        Storage::fake('local');

        $batch = (new CreateImportBatch)->execute(
            $this->createUser(),
            'PERSONAS',
            UploadedFile::fake()->createWithContent(
                'personas.xlsx',
                'contenido'
            )
        );

        self::assertNull(
            $batch->options
        );
    }

    public function test_rejects_empty_import_type_before_storing_file(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent(
            'personas.xlsx',
            'contenido'
        );

        try {
            (new CreateImportBatch)->execute(
                $this->createUser(),
                '   ',
                $file
            );
        } catch (InvalidArgumentException) {
            self::assertSame(
                [],
                Storage::disk('local')->allFiles()
            );

            return;
        }

        self::fail(
            'Un tipo de importacion vacio debe rechazarse.'
        );
    }

    public function test_database_failure_removes_stored_file(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent(
            'personas.xlsx',
            'contenido-transaccional'
        );

        try {
            (new CreateImportBatch)->execute(
                999999999,
                'PERSONAS',
                $file
            );
        } catch (QueryException) {
            self::assertSame(
                [],
                Storage::disk('local')->allFiles(
                    'imports'
                )
            );

            self::assertSame(
                0,
                ImportBatch::query()
                    ->where(
                        'file_sha256',
                        hash(
                            'sha256',
                            'contenido-transaccional'
                        )
                    )
                    ->count()
            );

            return;
        }

        self::fail(
            'La FK created_by debio provocar un error de persistencia.'
        );
    }

    private function createUser(): int
    {
        return (int) $this->db
            ->table('system.users')
            ->insertGetId([
                'name' => 'USUARIO CREACION IMPORTACION',
                'email' => Str::uuid()
                    .'@create-import.siga.test',
                'password' => Hash::make(
                    'ClaveSegura123!'
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function assertDatabaseRecordMatches(
        ImportBatch $batch
    ): void {
        $record = $this->db
            ->table('system.import_batches')
            ->where(
                'id',
                $batch->id
            )
            ->first();

        self::assertNotNull(
            $record
        );

        self::assertSame(
            $batch->uuid,
            $record->uuid
        );

        self::assertSame(
            'UPLOADED',
            $record->status
        );

        self::assertSame(
            $batch->storage_path,
            $record->storage_path
        );

        self::assertSame(
            $batch->file_sha256,
            $record->file_sha256
        );
    }
}

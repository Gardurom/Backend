<?php

namespace Tests\Functional\Importing;

use App\Actions\Importing\Personas\CreateAndValidatePersonImportBatch;
use App\Importing\ImportBatchStatus;
use App\Importing\ImportRowStatus;
use App\Importing\Personas\PersonImportTemplateV2;
use App\Models\ImportRow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Functional\FunctionalTestCase;

class CreateAndValidatePersonImportBatchTest extends FunctionalTestCase
{
    private ?string $temporaryFile = null;

    protected function tearDown(): void
    {
        if (
            $this->temporaryFile !== null
            && is_file($this->temporaryFile)
        ) {
            @unlink(
                $this->temporaryFile
            );
        }

        parent::tearDown();
    }

    public function test_creates_stages_and_validates_person_xlsx(): void
    {
        Storage::fake('local');

        $userId =
            $this->createUser();

        $template =
            new PersonImportTemplateV2;

        $file =
            $this->createUploadedWorkbook([
                $template->columns(),
                [
                    'MARIA',
                    'LOPEZ',
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                ],
            ]);

        $batch =
            $this->app
                ->make(
                    CreateAndValidatePersonImportBatch::class
                )
                ->execute(
                    $userId,
                    $file
                );

        self::assertSame(
            ImportBatchStatus::READY,
            $batch->status
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
            1,
            $batch->total_rows
        );

        self::assertSame(
            1,
            $batch->valid_rows
        );

        self::assertSame(
            0,
            $batch->invalid_rows
        );

        self::assertSame(
            '2',
            $batch->options['template_version']
        );

        self::assertSame(
            $template->columns(),
            $batch->options['source_columns']
        );

        Storage::disk('local')->assertExists(
            $batch->storage_path
        );

        $row =
            ImportRow::query()
                ->where(
                    'import_batch_id',
                    $batch->id
                )
                ->sole();

        self::assertSame(
            2,
            $row->row_number
        );

        self::assertSame(
            ImportRowStatus::VALID,
            $row->status
        );
    }

    private function createUser(): int
    {
        return (int) $this->db
            ->table('system.users')
            ->insertGetId([
                'name' => 'USUARIO ORQUESTACION IMPORTACION',
                'email' => Str::uuid()
                    .'@person-import-orchestration.siga.test',
                'password' => Hash::make(
                    'ClaveSegura123!'
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function createUploadedWorkbook(
        array $rows
    ): UploadedFile {
        $file =
            tempnam(
                sys_get_temp_dir(),
                'siga-person-import-'
            );

        if ($file === false) {
            self::fail(
                'No fue posible crear el archivo temporal.'
            );
        }

        @unlink(
            $file
        );

        $file .= '.xlsx';

        $writer =
            new Writer;

        $writer->openToFile(
            $file
        );

        foreach ($rows as $values) {
            $writer->addRow(
                Row::fromValues(
                    $values
                )
            );
        }

        $writer->close();

        $this->temporaryFile =
            $file;

        return new UploadedFile(
            $file,
            'personas.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}

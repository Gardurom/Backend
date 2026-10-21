<?php

namespace Tests\Functional\Importing;

use App\Actions\Importing\CreateImportBatch;
use App\Actions\Importing\Personas\CreateAndValidatePersonImportBatch;
use App\Actions\Importing\Personas\ValidatePersonImportBatch;
use App\Actions\Importing\StageImportRows;
use App\Importing\InvalidXlsxImportSource;
use App\Importing\Personas\InvalidPersonImportTemplate;
use App\Importing\Personas\PersonImportTemplateV2;
use App\Importing\Readers\XlsxImportSourceReader;
use App\Models\ImportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Functional\FunctionalTestCase;

class CreateAndValidatePersonImportBatchPreflightTest extends FunctionalTestCase
{
    /**
     * @var list<string>
     */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                @unlink(
                    $file
                );
            }
        }

        parent::tearDown();
    }

    public function test_rejects_wrong_template_before_creating_batch(): void
    {
        Storage::fake('local');

        $userId =
            $this->createUser();

        $file =
            $this->createUploadedWorkbook([
                [
                    'nombres',
                    'apellido_paterno',
                ],
                [
                    'MARIA',
                    'LOPEZ',
                ],
            ]);

        $before =
            ImportBatch::query()->count();

        try {
            $this->app
                ->make(
                    CreateAndValidatePersonImportBatch::class
                )
                ->execute(
                    $userId,
                    $file
                );
        } catch (InvalidPersonImportTemplate) {
            self::assertSame(
                $before,
                ImportBatch::query()->count()
            );

            self::assertSame(
                [],
                Storage::disk('local')->allFiles(
                    'imports'
                )
            );

            return;
        }

        self::fail(
            'La plantilla incorrecta debe rechazarse antes de crear el lote.'
        );
    }

    public function test_rejects_more_than_five_thousand_rows_before_creating_batch(): void
    {
        Storage::fake('local');

        $file =
            $this->createUploadedWorkbookWithDataRows(
                5001
            );

        $createBatch =
            Mockery::mock(
                CreateImportBatch::class
            );

        $createBatch
            ->shouldNotReceive(
                'execute'
            );

        $action =
            new CreateAndValidatePersonImportBatch(
                $createBatch,
                $this->app->make(
                    StageImportRows::class
                ),
                $this->app->make(
                    ValidatePersonImportBatch::class
                ),
                $this->app->make(
                    XlsxImportSourceReader::class
                ),
                new PersonImportTemplateV2
            );

        try {
            $action->execute(
                1,
                $file
            );
        } catch (InvalidXlsxImportSource $exception) {
            self::assertSame(
                'El archivo XLSX no puede contener mas de 5000 filas de datos.',
                $exception->getMessage()
            );

            return;
        }

        self::fail(
            'Un XLSX con mas de 5000 filas debe rechazarse durante el preflight.'
        );
    }

    public function test_rejects_xlsx_without_data_before_creating_batch(): void
    {
        Storage::fake('local');

        $userId =
            $this->createUser();

        $template =
            new PersonImportTemplateV2;

        $file =
            $this->createUploadedWorkbook([
                $template->columns(),
            ]);

        $before =
            ImportBatch::query()->count();

        try {
            $this->app
                ->make(
                    CreateAndValidatePersonImportBatch::class
                )
                ->execute(
                    $userId,
                    $file
                );
        } catch (InvalidXlsxImportSource) {
            self::assertSame(
                $before,
                ImportBatch::query()->count()
            );

            self::assertSame(
                [],
                Storage::disk('local')->allFiles(
                    'imports'
                )
            );

            return;
        }

        self::fail(
            'Un XLSX sin filas debe rechazarse antes de crear el lote.'
        );
    }

    private function createUser(): int
    {
        return (int) $this->db
            ->table('system.users')
            ->insertGetId([
                'name' => 'USUARIO PREFLIGHT IMPORTACION',
                'email' => Str::uuid()
                    .'@person-import-preflight.siga.test',
                'password' => Hash::make(
                    'ClaveSegura123!'
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function createUploadedWorkbookWithDataRows(
        int $count
    ): UploadedFile {
        $file =
            tempnam(
                sys_get_temp_dir(),
                'siga-person-row-limit-'
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

        $template =
            new PersonImportTemplateV2;

        $writer->addRow(
            Row::fromValues(
                $template->columns()
            )
        );

        for ($number = 1; $number <= $count; $number++) {
            $writer->addRow(
                Row::fromValues([
                    "PERSONA {$number}",
                    'APELLIDO',
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
                ])
            );
        }

        $writer->close();

        $this->temporaryFiles[] =
            $file;

        return new UploadedFile(
            $file,
            'personas.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
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
                'siga-person-preflight-'
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

        $this->temporaryFiles[] =
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

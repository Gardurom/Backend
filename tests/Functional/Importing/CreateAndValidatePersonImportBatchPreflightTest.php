<?php

namespace Tests\Functional\Importing;

use App\Actions\Importing\Personas\CreateAndValidatePersonImportBatch;
use App\Importing\InvalidXlsxImportSource;
use App\Importing\Personas\InvalidPersonImportTemplate;
use App\Importing\Personas\PersonImportTemplateV2;
use App\Models\ImportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

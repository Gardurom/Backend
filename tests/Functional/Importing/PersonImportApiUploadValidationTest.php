<?php

namespace Tests\Functional\Importing;

use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Functional\HttpFunctionalTestCase;

class PersonImportApiUploadValidationTest extends HttpFunctionalTestCase
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

    public function test_rejects_non_xlsx_file_as_validation_error(): void
    {
        Storage::fake('local');

        $user =
            $this->createAuthorizedUser(
                'import.upload.non-xlsx@siga.test'
            );

        $before =
            ImportBatch::query()->count();

        $this->actingAs(
            $user
        );

        $response =
            $this->post(
                '/api/personas/importaciones',
                [
                    'archivo' => UploadedFile::fake()->create(
                        'personas.txt',
                        1,
                        'text/plain'
                    ),
                ],
                [
                    'Accept' => 'application/json',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'archivo',
            ]);

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
    }

    public function test_rejects_xlsx_larger_than_five_megabytes(): void
    {
        Storage::fake('local');

        $user =
            $this->createAuthorizedUser(
                'import.upload.too-large@siga.test'
            );

        $before =
            ImportBatch::query()->count();

        $this->actingAs(
            $user
        );

        $response =
            $this->post(
                '/api/personas/importaciones',
                [
                    'archivo' => UploadedFile::fake()->create(
                        'personas.xlsx',
                        5121,
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                    ),
                ],
                [
                    'Accept' => 'application/json',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'archivo',
            ]);

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
    }

    public function test_rejects_corrupt_xlsx_as_validation_error(): void
    {
        Storage::fake('local');

        $user =
            $this->createAuthorizedUser(
                'import.upload.corrupt@siga.test'
            );

        $before =
            ImportBatch::query()->count();

        $file =
            $this->createCorruptXlsx();

        $this->actingAs(
            $user
        );

        $response =
            $this->post(
                '/api/personas/importaciones',
                [
                    'archivo' => $file,
                ],
                [
                    'Accept' => 'application/json',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'archivo',
            ]);

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
    }

    public function test_rejects_wrong_person_template_as_validation_error(): void
    {
        Storage::fake('local');

        $user =
            $this->createAuthorizedUser(
                'import.upload.template@siga.test'
            );

        $before =
            ImportBatch::query()->count();

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

        $this->actingAs(
            $user
        );

        $response =
            $this->post(
                '/api/personas/importaciones',
                [
                    'archivo' => $file,
                ],
                [
                    'Accept' => 'application/json',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'archivo',
            ]);

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
    }

    private function createAuthorizedUser(
        string $email
    ): User {
        $user = User::factory()->create([
            'name' => 'GESTOR VALIDACION UPLOAD',
            'email' => $email,
        ]);

        $roleId =
            $this->db
                ->table('system.roles')
                ->where(
                    'code',
                    'ROL_GESTOR_PERSONAS'
                )
                ->value('id');

        self::assertNotNull(
            $roleId,
            'Debe existir ROL_GESTOR_PERSONAS.'
        );

        $this->db
            ->table('system.user_roles')
            ->insert([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return $user;
    }

    private function createCorruptXlsx(): UploadedFile
    {
        $file =
            tempnam(
                sys_get_temp_dir(),
                'siga-person-corrupt-'
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

        $written =
            file_put_contents(
                $file,
                'CONTENIDO QUE NO ES UN ARCHIVO XLSX'
            );

        if ($written === false) {
            self::fail(
                'No fue posible crear el XLSX corrupto.'
            );
        }

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
                'siga-person-api-validation-'
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

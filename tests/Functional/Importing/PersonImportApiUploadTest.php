<?php

namespace Tests\Functional\Importing;

use App\Importing\Personas\PersonImportTemplateV2;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Functional\HttpFunctionalTestCase;

class PersonImportApiUploadTest extends HttpFunctionalTestCase
{
    private ?string $temporaryFile = null;

    protected function tearDown(): void
    {
        if ($this->temporaryFile !== null) {
            if (is_file($this->temporaryFile)) {
                @unlink(
                    $this->temporaryFile
                );
            }
        }

        parent::tearDown();
    }

    public function test_guest_cannot_upload_person_import(): void
    {
        $this->post(
            '/api/personas/importaciones',
            [],
            [
                'Accept' => 'application/json',
            ]
        )->assertUnauthorized();
    }

    public function test_authenticated_user_without_permission_cannot_upload(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO SIN PERMISO IMPORTACION',
            'email' => 'import.upload.forbidden@siga.test',
        ]);

        $this->actingAs(
            $user
        );

        $this->post(
            '/api/personas/importaciones',
            [],
            [
                'Accept' => 'application/json',
            ]
        )->assertForbidden();
    }

    public function test_authorized_user_must_provide_file(): void
    {
        $user =
            $this->createAuthorizedUser(
                'import.upload.required@siga.test'
            );

        $this->actingAs(
            $user
        );

        $this->post(
            '/api/personas/importaciones',
            [],
            [
                'Accept' => 'application/json',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'archivo',
            ]);
    }

    public function test_authorized_user_can_upload_valid_person_xlsx(): void
    {
        Storage::fake('local');

        $user =
            $this->createAuthorizedUser(
                'import.upload.success@siga.test'
            );

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
            ->assertCreated()
            ->assertJsonPath(
                'data.original_filename',
                'personas.xlsx'
            )
            ->assertJsonPath(
                'data.status',
                'READY'
            )
            ->assertJsonPath(
                'data.total_rows',
                1
            )
            ->assertJsonPath(
                'data.valid_rows',
                1
            )
            ->assertJsonPath(
                'data.invalid_rows',
                0
            )
            ->assertJsonPath(
                'data.processed_rows',
                0
            )
            ->assertJsonPath(
                'data.failed_rows',
                0
            );

        $payload =
            $response->json();

        self::assertIsArray(
            $payload
        );

        self::assertArrayHasKey(
            'data',
            $payload
        );

        self::assertIsString(
            $payload['data']['uuid']
        );

        self::assertNotSame(
            '',
            $payload['data']['uuid']
        );

        foreach (
            [
                'id',
                'import_type',
                'storage_disk',
                'storage_path',
                'file_sha256',
                'created_by',
            ] as $privateField
        ) {
            self::assertArrayNotHasKey(
                $privateField,
                $payload['data'],
                "El upload no debe exponer {$privateField}."
            );
        }

        $batch =
            ImportBatch::query()
                ->where(
                    'uuid',
                    $payload['data']['uuid']
                )
                ->sole();

        self::assertSame(
            $user->id,
            $batch->created_by
        );

        Storage::disk('local')->assertExists(
            $batch->storage_path
        );
    }

    private function createAuthorizedUser(
        string $email
    ): User {
        $user = User::factory()->create([
            'name' => 'GESTOR UPLOAD IMPORTACION',
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

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function createUploadedWorkbook(
        array $rows
    ): UploadedFile {
        $file =
            tempnam(
                sys_get_temp_dir(),
                'siga-person-api-upload-'
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

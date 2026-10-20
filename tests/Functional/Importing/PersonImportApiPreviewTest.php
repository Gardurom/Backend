<?php

namespace Tests\Functional\Importing;

use App\Importing\ImportBatchStatus;
use App\Importing\ImportRowStatus;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Functional\HttpFunctionalTestCase;

class PersonImportApiPreviewTest extends HttpFunctionalTestCase
{
    public function test_guest_cannot_view_person_import_preview(): void
    {
        $creator = User::factory()->create([
            'name' => 'CREADOR IMPORTACION GUEST',
            'email' => 'import.preview.guest@siga.test',
        ]);

        $batch =
            $this->createReadyBatch(
                $creator,
                1
            );

        $this->getJson(
            "/api/personas/importaciones/{$batch->uuid}"
        )->assertUnauthorized();
    }

    public function test_authenticated_user_without_import_permission_cannot_view_preview(): void
    {
        $user = User::factory()->create([
            'name' => 'USUARIO SIN PERMISO IMPORTACION',
            'email' => 'import.preview.forbidden@siga.test',
        ]);

        $batch =
            $this->createReadyBatch(
                $user,
                1
            );

        $this->actingAs(
            $user
        );

        $this->getJson(
            "/api/personas/importaciones/{$batch->uuid}"
        )->assertForbidden();
    }

    public function test_authorized_user_receives_safe_paginated_preview(): void
    {
        $user =
            $this->createAuthorizedUser(
                'import.preview.authorized@siga.test'
            );

        $batch =
            $this->createReadyBatch(
                $user,
                55
            );

        $this->actingAs(
            $user
        );

        $response =
            $this->getJson(
                "/api/personas/importaciones/{$batch->uuid}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.uuid',
                $batch->uuid
            )
            ->assertJsonPath(
                'data.original_filename',
                'personas-preview.xlsx'
            )
            ->assertJsonPath(
                'data.status',
                'READY'
            )
            ->assertJsonPath(
                'data.total_rows',
                55
            )
            ->assertJsonPath(
                'data.valid_rows',
                54
            )
            ->assertJsonPath(
                'data.invalid_rows',
                1
            )
            ->assertJsonPath(
                'data.processed_rows',
                0
            )
            ->assertJsonPath(
                'data.failed_rows',
                0
            )
            ->assertJsonPath(
                'meta.current_page',
                1
            )
            ->assertJsonPath(
                'meta.per_page',
                50
            )
            ->assertJsonPath(
                'meta.last_page',
                2
            )
            ->assertJsonPath(
                'meta.total',
                55
            )
            ->assertJsonCount(
                50,
                'rows'
            )
            ->assertJsonPath(
                'rows.0.row_number',
                2
            )
            ->assertJsonPath(
                'rows.0.status',
                'INVALID'
            )
            ->assertJsonPath(
                'rows.0.validation_errors.nombres.0',
                'Error controlado de preview.'
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
                "El preview no debe exponer {$privateField}."
            );
        }

        self::assertArrayNotHasKey(
            'id',
            $payload['rows'][0]
        );

        self::assertArrayNotHasKey(
            'import_batch_id',
            $payload['rows'][0]
        );
    }

    public function test_preview_rows_are_ordered_and_paginated(): void
    {
        $user =
            $this->createAuthorizedUser(
                'import.preview.page2@siga.test'
            );

        $batch =
            $this->createReadyBatch(
                $user,
                55
            );

        $this->actingAs(
            $user
        );

        $response =
            $this->getJson(
                "/api/personas/importaciones/{$batch->uuid}?page=2"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.current_page',
                2
            )
            ->assertJsonPath(
                'meta.per_page',
                50
            )
            ->assertJsonPath(
                'meta.total',
                55
            )
            ->assertJsonCount(
                5,
                'rows'
            )
            ->assertJsonPath(
                'rows.0.row_number',
                52
            )
            ->assertJsonPath(
                'rows.4.row_number',
                56
            );
    }

    public function test_person_preview_does_not_expose_other_import_types(): void
    {
        $user =
            $this->createAuthorizedUser(
                'import.preview.other-type@siga.test'
            );

        $batch =
            $this->createReadyBatch(
                $user,
                1,
                'OTRO'
            );

        $this->actingAs(
            $user
        );

        $this->getJson(
            "/api/personas/importaciones/{$batch->uuid}"
        )->assertNotFound();
    }

    private function createAuthorizedUser(
        string $email
    ): User {
        $user = User::factory()->create([
            'name' => 'GESTOR PREVIEW IMPORTACION',
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

    private function createReadyBatch(
        User $creator,
        int $totalRows,
        string $importType = 'PERSONAS'
    ): ImportBatch {
        $invalidRows =
            $totalRows > 0
                ? 1
                : 0;

        $batch =
            ImportBatch::query()->create([
                'uuid' => (string) Str::uuid(),
                'import_type' => $importType,
                'status' => ImportBatchStatus::READY,
                'original_filename' => 'personas-preview.xlsx',
                'storage_disk' => 'local',
                'storage_path' => 'imports/personas/preview/source',
                'file_sha256' => hash(
                    'sha256',
                    Str::uuid()->toString()
                ),
                'options' => [
                    'template_version' => '2',
                ],
                'total_rows' => $totalRows,
                'valid_rows' => $totalRows - $invalidRows,
                'invalid_rows' => $invalidRows,
                'processed_rows' => 0,
                'failed_rows' => 0,
                'created_by' => $creator->id,
            ]);

        for (
            $index = 0;
            $index < $totalRows;
            $index++
        ) {
            $status =
                $index === 0
                    ? ImportRowStatus::INVALID
                    : ImportRowStatus::VALID;

            ImportRow::query()->create([
                'import_batch_id' => $batch->id,
                'row_number' => $index + 2,
                'status' => $status,
                'raw_data' => [
                    'nombres' => 'PERSONA PREVIEW '
                        .str_pad(
                            (string) $index,
                            3,
                            '0',
                            STR_PAD_LEFT
                        ),
                ],
                'normalized_data' => [
                    'nombres' => 'PERSONA PREVIEW '
                        .str_pad(
                            (string) $index,
                            3,
                            '0',
                            STR_PAD_LEFT
                        ),
                ],
                'validation_errors' => $status === ImportRowStatus::INVALID
                        ? [
                            'nombres' => [
                                'Error controlado de preview.',
                            ],
                        ]
                        : [],
                'result_data' => null,
            ]);
        }

        return $batch;
    }
}

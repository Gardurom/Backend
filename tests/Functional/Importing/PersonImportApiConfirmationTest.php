<?php

namespace Tests\Functional\Importing;

use App\Importing\ImportBatchStatus;
use App\Jobs\Importing\Personas\ProcessPersonImportBatchJob;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Functional\HttpFunctionalTestCase;

class PersonImportApiConfirmationTest extends HttpFunctionalTestCase
{
    public function test_guest_cannot_confirm_person_import(): void
    {
        $this->postJson(
            '/api/personas/importaciones/'
            .'01900000-0000-7000-8000-000000000001'
            .'/confirmar'
        )->assertUnauthorized();
    }

    public function test_authenticated_user_without_permission_cannot_confirm(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'name' => 'USUARIO SIN PERMISO CONFIRMACION',
            'email' => 'import.confirm.forbidden@siga.test',
        ]);

        $batch =
            $this->createReadyBatch(
                $user,
                validRows: 1
            );

        $response =
            $this->postAsRecentlyReauthenticated(
                $user,
                '/api/personas/importaciones/'
                .$batch->uuid
                .'/confirmar'
            );

        $response->assertForbidden();

        $batch->refresh();

        self::assertSame(
            ImportBatchStatus::READY,
            $batch->status
        );

        self::assertNull(
            $batch->confirmed_at
        );

        Queue::assertNothingPushed();
    }

    public function test_confirmation_requires_recent_reauthentication(): void
    {
        Queue::fake();

        $user =
            $this->createAuthorizedUser(
                'import.confirm.reauthentication@siga.test'
            );

        $batch =
            $this->createReadyBatch(
                $user,
                validRows: 1
            );

        $this->actingAs(
            $user
        );

        $csrfToken =
            'csrf-person-import-confirm-reauthentication-siga';

        $response =
            $this
                ->withCredentials()
                ->withHeader(
                    'Origin',
                    'http://localhost'
                )
                ->withSession([
                    '_token' => $csrfToken,
                    'siga_authenticated_at' => now()->timestamp,
                ])
                ->withHeader(
                    'X-CSRF-TOKEN',
                    $csrfToken
                )
                ->postJson(
                    '/api/personas/importaciones/'
                    .$batch->uuid
                    .'/confirmar'
                );

        $response
            ->assertStatus(423)
            ->assertJson([
                'message' => 'Reauthentication required.',
            ]);

        $batch->refresh();

        self::assertSame(
            ImportBatchStatus::READY,
            $batch->status
        );

        self::assertNull(
            $batch->confirmed_at
        );

        Queue::assertNothingPushed();
    }

    public function test_authorized_user_can_confirm_ready_person_import(): void
    {
        Queue::fake();

        $user =
            $this->createAuthorizedUser(
                'import.confirm.success@siga.test'
            );

        $batch =
            $this->createReadyBatch(
                $user,
                validRows: 2,
                invalidRows: 1
            );

        $response =
            $this->postAsRecentlyReauthenticated(
                $user,
                '/api/personas/importaciones/'
                .$batch->uuid
                .'/confirmar'
            );

        $response
            ->assertStatus(202)
            ->assertJsonPath(
                'data.uuid',
                $batch->uuid
            )
            ->assertJsonPath(
                'data.status',
                'QUEUED'
            )
            ->assertJsonPath(
                'data.total_rows',
                3
            )
            ->assertJsonPath(
                'data.valid_rows',
                2
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
            );

        $payload =
            $response->json();

        self::assertIsArray(
            $payload
        );

        self::assertIsArray(
            $payload['data']
        );

        self::assertNotNull(
            $payload['data']['confirmed_at']
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
                "La confirmación no debe exponer {$privateField}."
            );
        }

        $batch->refresh();

        self::assertSame(
            ImportBatchStatus::QUEUED,
            $batch->status
        );

        self::assertNotNull(
            $batch->confirmed_at
        );

        Queue::assertPushed(
            ProcessPersonImportBatchJob::class,
            1
        );

        Queue::assertPushed(
            ProcessPersonImportBatchJob::class,
            static fn (
                ProcessPersonImportBatchJob $job
            ): bool => $job->batchId
                === $batch->id
        );
    }

    public function test_ready_batch_without_valid_rows_returns_conflict(): void
    {
        Queue::fake();

        $user =
            $this->createAuthorizedUser(
                'import.confirm.no-valid@siga.test'
            );

        $batch =
            $this->createReadyBatch(
                $user,
                validRows: 0,
                invalidRows: 2
            );

        $response =
            $this->postAsRecentlyReauthenticated(
                $user,
                '/api/personas/importaciones/'
                .$batch->uuid
                .'/confirmar'
            );

        $response
            ->assertConflict()
            ->assertJson([
                'message' => 'El lote debe contener al menos una fila valida para ser confirmado.',
            ]);

        $batch->refresh();

        self::assertSame(
            ImportBatchStatus::READY,
            $batch->status
        );

        self::assertNull(
            $batch->confirmed_at
        );

        Queue::assertNothingPushed();
    }

    public function test_confirmation_does_not_expose_other_import_types(): void
    {
        Queue::fake();

        $user =
            $this->createAuthorizedUser(
                'import.confirm.other-type@siga.test'
            );

        $batch =
            $this->createReadyBatch(
                $user,
                validRows: 1,
                importType: 'OTRO'
            );

        $response =
            $this->postAsRecentlyReauthenticated(
                $user,
                '/api/personas/importaciones/'
                .$batch->uuid
                .'/confirmar'
            );

        $response->assertNotFound();

        $batch->refresh();

        self::assertSame(
            ImportBatchStatus::READY,
            $batch->status
        );

        self::assertNull(
            $batch->confirmed_at
        );

        Queue::assertNothingPushed();
    }

    private function createAuthorizedUser(
        string $email
    ): User {
        $user = User::factory()->create([
            'name' => 'GESTOR CONFIRMACION IMPORTACION',
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
        int $validRows,
        int $invalidRows = 0,
        string $importType = 'PERSONAS'
    ): ImportBatch {
        return ImportBatch::query()->create([
            'uuid' => (string) Str::uuid(),
            'import_type' => $importType,
            'status' => ImportBatchStatus::READY,
            'original_filename' => 'personas.xlsx',
            'storage_disk' => 'local',
            'storage_path' => 'imports/'
                .Str::uuid()
                .'/source',
            'file_sha256' => hash(
                'sha256',
                Str::uuid()->toString()
            ),
            'options' => [
                'template_version' => '2',
            ],
            'total_rows' => $validRows + $invalidRows,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'processed_rows' => 0,
            'failed_rows' => 0,
            'created_by' => $creator->id,
        ]);
    }

    private function postAsRecentlyReauthenticated(
        User $user,
        string $uri
    ) {
        $this->actingAs(
            $user
        );

        $csrfToken =
            'csrf-person-import-confirmation-siga';

        return $this
            ->withCredentials()
            ->withHeader(
                'Origin',
                'http://localhost'
            )
            ->withSession([
                '_token' => $csrfToken,
                'siga_authenticated_at' => now()->timestamp,
                'siga_reauthenticated_at' => now()->timestamp,
            ])
            ->withHeader(
                'X-CSRF-TOKEN',
                $csrfToken
            )
            ->postJson(
                $uri
            );
    }
}

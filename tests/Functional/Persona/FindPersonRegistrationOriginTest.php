<?php

namespace Tests\Functional\Persona;

use App\Actions\Persona\FindPersonRegistrationOrigin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Functional\HttpFunctionalTestCase;

class FindPersonRegistrationOriginTest extends HttpFunctionalTestCase
{
    public function test_person_without_origin_evidence_is_not_assumed_individual(): void
    {
        $personId = $this->createPerson();

        $result = app(FindPersonRegistrationOrigin::class)
            ->execute($personId);

        self::assertSame(
            'NO_DETERMINADO',
            $result['forma_registro']
        );

        self::assertNull($result['archivo_origen']);
        self::assertNull($result['fila_archivo']);
        self::assertNull($result['lote_uuid']);
        self::assertNotNull($result['fecha_registro']);
    }

    public function test_mass_import_identifies_file_and_row(): void
    {
        $user = User::factory()->create();
        $personId = $this->createPerson();

        $batchId = $this->createBatch($user->id);

        $this->createProcessedRow(
            $batchId,
            $personId,
            25
        );

        $result = app(FindPersonRegistrationOrigin::class)
            ->execute($personId);

        self::assertSame(
            'MASIVO',
            $result['forma_registro']
        );

        self::assertSame(
            'personas-trazabilidad.xlsx',
            $result['archivo_origen']
        );

        self::assertSame(25, $result['fila_archivo']);
        self::assertNotNull($result['lote_uuid']);
    }

    public function test_multiple_import_matches_do_not_assign_arbitrary_origin(): void
    {
        $user = User::factory()->create();
        $personId = $this->createPerson();

        $firstBatch = $this->createBatch($user->id);
        $secondBatch = $this->createBatch($user->id);

        $this->createProcessedRow($firstBatch, $personId, 2);
        $this->createProcessedRow($secondBatch, $personId, 3);

        $result = app(FindPersonRegistrationOrigin::class)
            ->execute($personId);

        self::assertSame(
            'NO_DETERMINADO',
            $result['forma_registro']
        );

        self::assertNull($result['archivo_origen']);
        self::assertNull($result['fila_archivo']);
    }

    public function test_explicit_individual_origin_is_recognized(): void
    {
        $user = User::factory()->create();
        $personId = $this->createPerson();

        $this->createOriginActivity(
            $user->id,
            $personId,
            'INDIVIDUAL'
        );

        $result = app(FindPersonRegistrationOrigin::class)
            ->execute($personId);

        self::assertSame(
            'INDIVIDUAL',
            $result['forma_registro']
        );

        self::assertSame(
            $user->name,
            $result['registrado_por']
        );

        self::assertNull($result['archivo_origen']);
        self::assertNull($result['fila_archivo']);
        self::assertNull($result['lote_uuid']);
    }

    public function test_explicit_mass_origin_with_processed_row_is_recognized(): void
    {
        $user = User::factory()->create();
        $personId = $this->createPerson();

        $this->createOriginActivity(
            $user->id,
            $personId,
            'MASIVO'
        );

        $batchId = $this->createBatch($user->id);

        $this->createProcessedRow(
            $batchId,
            $personId,
            25
        );

        $result = app(FindPersonRegistrationOrigin::class)
            ->execute($personId);

        self::assertSame(
            'MASIVO',
            $result['forma_registro']
        );

        self::assertSame(
            $user->name,
            $result['registrado_por']
        );

        self::assertSame(
            'personas-trazabilidad.xlsx',
            $result['archivo_origen']
        );

        self::assertSame(25, $result['fila_archivo']);
        self::assertNotNull($result['lote_uuid']);
    }

    public function test_individual_audit_conflicting_with_import_is_not_assumed_valid(): void
    {
        $user = User::factory()->create();
        $personId = $this->createPerson();

        $this->createOriginActivity(
            $user->id,
            $personId,
            'INDIVIDUAL'
        );

        $batchId = $this->createBatch($user->id);

        $this->createProcessedRow(
            $batchId,
            $personId,
            25
        );

        $result = app(FindPersonRegistrationOrigin::class)
            ->execute($personId);

        self::assertSame(
            'NO_DETERMINADO',
            $result['forma_registro']
        );

        self::assertNull($result['archivo_origen']);
        self::assertNull($result['fila_archivo']);
        self::assertNull($result['lote_uuid']);
    }

    public function test_mass_audit_without_processed_row_is_not_assumed_valid(): void
    {
        $user = User::factory()->create();
        $personId = $this->createPerson();

        $this->createOriginActivity(
            $user->id,
            $personId,
            'MASIVO'
        );

        $result = app(FindPersonRegistrationOrigin::class)
            ->execute($personId);

        self::assertSame(
            'NO_DETERMINADO',
            $result['forma_registro']
        );

        self::assertNull($result['archivo_origen']);
        self::assertNull($result['fila_archivo']);
        self::assertNull($result['lote_uuid']);
    }

    private function createOriginActivity(
        int $userId,
        string $personId,
        string $origin
    ): void {
        DB::table('system.activities')->insert([
            'id_usuario' => $userId,
            'entidad' => 'PERSONA',
            'id_entidad' => $personId,
            'accion' => 'CREACION',
            'datos_anteriores' => null,
            'datos_nuevos' => json_encode(
                ['origen_registro' => $origin],
                JSON_THROW_ON_ERROR
            ),
            'campos_modificados' => json_encode(
                [],
                JSON_THROW_ON_ERROR
            ),
        ]);
    }
    private function createPerson(): string
    {
        $person = DB::table('institutional.persons')
            ->insertGetId([
                'nombres' => 'PERSONA TRAZABILIDAD',
                'apellido_paterno' => 'PRUEBA',
            ], 'id_persona');

        return (string) $person;
    }

    private function createBatch(int $userId): int
    {
        return (int) DB::table('system.import_batches')
            ->insertGetId([
                'uuid' => (string) Str::uuid(),
                'import_type' => 'PERSONAS',
                'status' => 'COMPLETED',
                'original_filename' => 'personas-trazabilidad.xlsx',
                'storage_disk' => 'local',
                'storage_path' => 'test/trazabilidad.xlsx',
                'file_sha256' => str_repeat('a', 64),
                'total_rows' => 1,
                'valid_rows' => 1,
                'invalid_rows' => 0,
                'processed_rows' => 1,
                'failed_rows' => 0,
                'created_by' => $userId,
            ]);
    }

    private function createProcessedRow(
        int $batchId,
        string $personId,
        int $rowNumber
    ): void {
        DB::table('system.import_rows')->insert([
            'import_batch_id' => $batchId,
            'row_number' => $rowNumber,
            'status' => 'PROCESSED',
            'raw_data' => json_encode([
                'nombres' => 'PERSONA TRAZABILIDAD',
            ], JSON_THROW_ON_ERROR),
            'normalized_data' => json_encode([
                'nombres' => 'PERSONA TRAZABILIDAD',
            ], JSON_THROW_ON_ERROR),
            'validation_errors' => json_encode([], JSON_THROW_ON_ERROR),
            'result_data' => json_encode([
                'id_persona' => $personId,
            ], JSON_THROW_ON_ERROR),
        ]);
    }
}
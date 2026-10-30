<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization;

use App\Actions\User\RecordUserRoleActivity;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\TestCase;

final class RoleActivityAuditTest extends TestCase
{
    private Container $previousContainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();

        $container = new Container();
        Container::setInstance($container);
        Facade::setFacadeApplication($container);
        Facade::clearResolvedInstances();
    }

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } finally {
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($this->previousContainer);
            Container::setInstance($this->previousContainer);
            parent::tearDown();
        }
    }

    private function expectActivity(
        int $actor,
        int $user,
        array $old,
        array $new
    ): void {
        $builder = Mockery::mock();

        $builder->shouldReceive('insert')
            ->once()
            ->with(Mockery::on(
                static function (array $row) use (
                    $actor,
                    $user,
                    $old,
                    $new
                ): bool {
                    self::assertSame(
                        $actor,
                        $row['id_usuario'],
                        'La actividad debe identificar al actor correcto.'
                    );

                    return $row['id_usuario'] === $actor
                        && $row['entidad'] === 'USUARIO_ROL'
                        && $row['id_entidad'] === (string) $user
                        && $row['accion'] === 'ACTUALIZACION'
                        && !array_key_exists('motivo', $row)
                        && json_decode(
                            $row['datos_anteriores'],
                            true,
                            512,
                            JSON_THROW_ON_ERROR
                        ) === $old
                        && json_decode(
                            $row['datos_nuevos'],
                            true,
                            512,
                            JSON_THROW_ON_ERROR
                        ) === $new
                        && json_decode(
                            $row['campos_modificados'],
                            true,
                            512,
                            JSON_THROW_ON_ERROR
                        ) === ['role_id', 'role_code'];
                }
            ))
            ->andReturn(true);

        $db = Mockery::mock();

        $db->shouldReceive('table')
            ->once()
            ->with('system.activities')
            ->andReturn($builder);

        Container::getInstance()->instance('db', $db);
    }

    public function test_assignment_records_the_authenticated_actor(): void
    {
        $this->expectActivity(
            1,
            25,
            ['role_id' => null, 'role_code' => null],
            ['role_id' => 7, 'role_code' => 'ROL_CONSULTA_PERSONAS']
        );

        (new RecordUserRoleActivity())->record(
            1, 25, 7, 'ROL_CONSULTA_PERSONAS', true
        );
    }

    public function test_removal_preserves_existing_audit_contract(): void
    {
        $this->expectActivity(
            1,
            25,
            ['role_id' => 7, 'role_code' => 'ROL_CONSULTA_PERSONAS'],
            ['role_id' => null, 'role_code' => null]
        );

        (new RecordUserRoleActivity())->record(
            1, 25, 7, 'ROL_CONSULTA_PERSONAS', false
        );
    }

    public function test_automatic_operation_marks_technical_actor(): void
    {
        $this->expectActivity(
            25,
            25,
            ['role_id' => null, 'role_code' => null],
            [
                'role_id' => 7,
                'role_code' => 'ROL_CONSULTA_PERSONAS',
                'origen' => 'REFERENCIA_TECNICA',
            ]
        );

        (new RecordUserRoleActivity())->record(
            null, 25, 7, 'ROL_CONSULTA_PERSONAS', true
        );
    }

    public function test_audit_failure_propagates_to_transaction(): void
    {
        $builder = Mockery::mock();

        $builder->shouldReceive('insert')
            ->once()
            ->andThrow(new \RuntimeException('AUDIT_FAILURE'));

        $db = Mockery::mock();

        $db->shouldReceive('table')
            ->once()
            ->with('system.activities')
            ->andReturn($builder);

        Container::getInstance()->instance('db', $db);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AUDIT_FAILURE');

        (new RecordUserRoleActivity())->record(
            1, 25, 7, 'ROL_CONSULTA_PERSONAS', true
        );
    }

    public function test_individual_actions_audit_only_effective_changes(): void
    {
        $assign = $this->source('app/Actions/User/AssignRole.php');
        $remove = $this->source('app/Actions/User/RemoveRole.php');

        self::assertStringContainsString(
            'if ($inserted === 1)', $assign
        );
        self::assertStringContainsString(
            'if ($deleted > 0)', $remove
        );
        self::assertStringContainsString(
            'RecordUserRoleActivity::class)->record(', $assign
        );
        self::assertStringContainsString(
            'RecordUserRoleActivity::class)->record(', $remove
        );
    }

    public function test_bulk_operations_share_one_recorder(): void
    {
        $assign = $this->source(
            'app/Http/Controllers/Api/BulkUserRoleController.php'
        );

        $remove = $this->source(
            'app/Http/Controllers/Api/BulkUserRoleRemovalController.php'
        );

        self::assertStringContainsString(
            'if ($inserted === 1)', $assign
        );
        self::assertStringContainsString(
            'if ($deleted > 0)', $remove
        );
        self::assertStringContainsString(
            'RecordUserRoleActivity::class)->record(', $assign
        );
        self::assertStringContainsString(
            'RecordUserRoleActivity::class)->record(', $remove
        );
        self::assertStringNotContainsString(
            "DB::table('system.activities')->insert", $remove
        );
    }

    public function test_controller_passes_actor_to_both_actions(): void
    {
        $source = $this->source(
            'app/Http/Controllers/Api/UserRoleController.php'
        );

        self::assertSame(
            2,
            substr_count(
                $source,
                '(int) $request->user()->getAuthIdentifier()'
            )
        );
    }

    public function test_recorder_does_not_use_motivo_field(): void
    {
        $source = $this->source(
            'app/Actions/User/RecordUserRoleActivity.php'
        );

        self::assertStringNotContainsString(
            "'motivo' =>", $source
        );
    }

    private function source(string $file): string
    {
        $path = dirname(__DIR__, 3).'/'.$file;
        $source = file_get_contents($path);

        self::assertNotFalse($source);

        return $source;
    }
}
<?php

namespace Tests\Unit\Geography;

use App\Geography\MexicoGeographyLoader;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SeedMexicoGeographyCommandTest extends TestCase
{
    public function test_consultation_does_not_execute_loader(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection->method('getDriverName')->willReturn('pgsql');

        $connection->expects(self::exactly(2))
            ->method('selectOne')
            ->willReturnCallback(
                static function (string $query): object {
                    if (str_contains($query, 'institutional.countries')) {
                        return (object) ['total' => 0];
                    }

                    if (str_contains($query, 'institutional.territories')) {
                        return (object) ['total' => 0];
                    }

                    self::fail('Consulta inesperada.');
                }
            );

        $connection->expects(self::never())->method('insert');
        $connection->expects(self::never())->method('statement');

        DB::shouldReceive('connection')
            ->once()
            ->andReturn($connection);

        $loader = $this->createMock(MexicoGeographyLoader::class);
        $loader->expects(self::never())->method('execute');

        $this->app->instance(MexicoGeographyLoader::class, $loader);

        $this->artisan('siga:seed-mexico-geography')
            ->expectsOutput('Carga geográfica de México')
            ->expectsOutput('Modo de consulta: no se modificarán registros.')
            ->expectsOutput('Países: 0')
            ->expectsOutput('Territorios: 0')
            ->assertExitCode(0);
    }

    public function test_apply_rejects_non_postgresql_connection(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection->method('getDriverName')->willReturn('sqlite');

        $connection->expects(self::never())->method('selectOne');
        $connection->expects(self::never())->method('insert');

        DB::shouldReceive('connection')
            ->once()
            ->andReturn($connection);

        $loader = $this->createMock(MexicoGeographyLoader::class);
        $loader->expects(self::never())->method('execute');

        $this->app->instance(MexicoGeographyLoader::class, $loader);

        $this->artisan('siga:seed-mexico-geography', ['--apply' => true])
            ->expectsOutput('La carga geográfica requiere PostgreSQL.')
            ->assertExitCode(1);
    }

    public function test_apply_rejects_unauthorized_identity(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection->method('getDriverName')->willReturn('pgsql');

        $connection->expects(self::once())
            ->method('selectOne')
            ->willReturn((object) [
                'login_role' => 'siga_app',
                'effective_role' => 'siga_app',
            ]);

        $connection->expects(self::never())->method('insert');

        DB::shouldReceive('connection')
            ->once()
            ->andReturn($connection);

        $loader = $this->createMock(MexicoGeographyLoader::class);
        $loader->expects(self::never())->method('execute');

        $this->app->instance(MexicoGeographyLoader::class, $loader);

        $this->artisan('siga:seed-mexico-geography', ['--apply' => true])
            ->expectsOutput(
                'La carga geográfica requiere la conexión siga_migrator.'
            )
            ->assertExitCode(1);
    }

    public function test_apply_delegates_to_loader_when_authorized(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection->method('getDriverName')->willReturn('pgsql');

        $connection->expects(self::once())
            ->method('selectOne')
            ->willReturn((object) [
                'login_role' => 'siga_migrator',
                'effective_role' => 'siga_migrator',
            ]);

        $connection->expects(self::never())->method('insert');

        DB::shouldReceive('connection')
            ->once()
            ->andReturn($connection);

        $loader = $this->createMock(MexicoGeographyLoader::class);
        $loader->expects(self::once())->method('execute');

        $this->app->instance(MexicoGeographyLoader::class, $loader);

        $this->artisan('siga:seed-mexico-geography', ['--apply' => true])
            ->expectsOutput(
                'La carga geográfica de México finalizó correctamente.'
            )
            ->assertExitCode(0);
    }
}
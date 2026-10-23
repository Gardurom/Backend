<?php

namespace Tests\Unit\Geography;

use App\Geography\MexicoGeographyCatalog;
use App\Geography\SeedMexicoGeography;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SeedMexicoGeographyTerritoriesTest extends TestCase
{
    private const COUNTRY_ID = '00000000-0000-7000-8000-000000000001';

    public function test_inserts_all_32_federal_entities(): void
    {
        $inserted = [];
        $connection = $this->connectionWithTerritories([]);

        $connection->expects(self::exactly(32))
            ->method('insert')
            ->willReturnCallback(
                static function (string $query, array $bindings) use (&$inserted): bool {
                    self::assertStringContainsString(
                        'institutional.territories',
                        $query
                    );

                    $inserted[] = $bindings;

                    return true;
                }
            );

        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');

        (new SeedMexicoGeography($connection))->execute();

        self::assertCount(32, $inserted);

        $expected = MexicoGeographyCatalog::territories();

        self::assertSame(
            array_column($expected, 'clave_oficial'),
            array_column($inserted, 3)
        );

        foreach ($inserted as $bindings) {
            self::assertSame(self::COUNTRY_ID, $bindings[0]);
            self::assertNull($bindings[1]);
            self::assertSame('ENTIDAD_FEDERATIVA', $bindings[2]);
            self::assertTrue($bindings[5]);
        }
    }

    public function test_existing_32_entities_are_not_inserted_again(): void
    {
        $territories = array_map(
            static fn (array $territory): object => self::territoryRecord(
                $territory
            ),
            MexicoGeographyCatalog::territories()
        );

        $connection = $this->connectionWithTerritories($territories);

        $connection->expects(self::never())->method('insert');
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');

        (new SeedMexicoGeography($connection))->execute();
    }

    public function test_inserts_only_missing_entities(): void
    {
        $catalog = MexicoGeographyCatalog::territories();

        $existing = array_map(
            static fn (array $territory): object =>
                self::territoryRecord($territory),
            array_slice($catalog, 0, 10)
        );

        $connection = $this->connectionWithTerritories($existing);
        $insertedKeys = [];

        $connection->expects(self::exactly(22))
            ->method('insert')
            ->willReturnCallback(
                static function (string $query, array $bindings) use (&$insertedKeys): bool {
                    self::assertStringContainsString(
                        'institutional.territories',
                        $query
                    );

                    $insertedKeys[] = $bindings[3];

                    return true;
                }
            );

        $connection->expects(self::once())->method('commit');

        (new SeedMexicoGeography($connection))->execute();

        self::assertSame(
            array_column(array_slice($catalog, 10), 'clave_oficial'),
            $insertedKeys
        );
    }

    public function test_rejects_duplicate_official_keys(): void
    {
        $territory = self::territoryRecord(
            MexicoGeographyCatalog::territories()[0]
        );

        $connection = $this->connectionWithTerritories([
            $territory,
            clone $territory,
        ]);

        $connection->expects(self::never())->method('insert');
        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::never())->method('commit');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('duplicada');

        (new SeedMexicoGeography($connection))->execute();
    }

    public function test_rejects_incompatible_entity_name(): void
    {
        $territory = self::territoryRecord(
            MexicoGeographyCatalog::territories()[0]
        );

        $territory->nombre = 'Nombre incorrecto';

        $this->assertInvalidTerritory($territory, 'nombre');
    }

    public function test_rejects_entity_with_parent_territory(): void
    {
        $territory = self::territoryRecord(
            MexicoGeographyCatalog::territories()[0]
        );

        $territory->id_territorio_padre =
            '00000000-0000-7000-8000-000000000003';

        $this->assertInvalidTerritory(
            $territory,
            'id_territorio_padre'
        );
    }

    private function assertInvalidTerritory(
        object $territory,
        string $field
    ): void {
        $connection = $this->connectionWithTerritories([$territory]);

        $connection->expects(self::never())->method('insert');
        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::never())->method('commit');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($field);

        (new SeedMexicoGeography($connection))->execute();
    }
    private function connectionWithTerritories(
        array $territories
    ): ConnectionInterface {
        $connection = $this->createMock(ConnectionInterface::class);

        $transactionLevel = 0;

        $connection->method('transactionLevel')
            ->willReturnCallback(
                static function () use (&$transactionLevel): int {
                    return $transactionLevel;
                }
            );

        $connection->expects(self::once())
            ->method('beginTransaction')
            ->willReturnCallback(
                static function () use (&$transactionLevel): void {
                    $transactionLevel = 1;
                }
            );

        $connection->method('commit')
            ->willReturnCallback(
                static function () use (&$transactionLevel): void {
                    $transactionLevel = 0;
                }
            );

        $connection->method('rollBack')
            ->willReturnCallback(
                static function () use (&$transactionLevel): void {
                    $transactionLevel = 0;
                }
            );

        $connection->method('statement')->willReturn(true);

        $connection->method('selectOne')
            ->willReturnCallback(
                static function (string $query): object {
                    if (str_contains($query, 'session_user')) {
                        return (object) [
                            'login_role' => 'siga_migrator',
                            'effective_role' => 'siga_migrator',
                        ];
                    }

                    if (str_contains($query, 'institutional.countries')) {
                        $country = MexicoGeographyCatalog::country();

                        return (object) [
                            'id_pais' => self::COUNTRY_ID,
                            'codigo_alpha2' => $country['codigo_alpha2'],
                            'codigo_alpha3' => $country['codigo_alpha3'],
                            'codigo_num3' => $country['codigo_num3'],
                            'nombre' => $country['nombre'],
                            'activo' => $country['activo'],
                        ];
                    }

                    throw new RuntimeException('Consulta inesperada.');
                }
            );

        $connection->method('select')
            ->willReturn($territories);

        return $connection;
    }

    private static function territoryRecord(array $territory): object
    {
        return (object) [
            'id_territorio' => '00000000-0000-7000-8000-000000000002',
            'id_pais' => self::COUNTRY_ID,
            'id_territorio_padre' => null,
            'tipo_territorio' => $territory['tipo_territorio'],
            'clave_oficial' => $territory['clave_oficial'],
            'nombre' => $territory['nombre'],
            'activo' => $territory['activo'],
        ];
    }
}
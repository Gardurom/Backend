<?php

namespace Tests\Unit\Geography;

use App\Geography\MexicoGeographyCatalog;
use App\Geography\SeedMexicoGeography;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SeedMexicoGeographyCountryTest extends TestCase
{
    private const COUNTRY_ID = '00000000-0000-7000-8000-000000000001';

    public function test_inserts_mexico_when_missing(): void
    {
        $connection = $this->administrativeConnection();

        $country = MexicoGeographyCatalog::country();
        $countryQueries = 0;
        $inserted = [];

        $connection->method('selectOne')
            ->willReturnCallback(
                static function (string $query) use (
                    &$countryQueries,
                    $country
                ): ?object {
                    if (str_contains($query, 'session_user')) {
                        return self::administrativeIdentity();
                    }

                    if (str_contains($query, 'codigo_alpha2 = ?')) {
                        return null;
                    }

                    if (str_contains($query, 'institutional.countries')) {
                        $countryQueries++;

                        return $countryQueries === 1
                            ? null
                            : self::countryRecord($country);
                    }

                    throw new RuntimeException('Consulta inesperada.');
                }
            );

        $connection->method('select')->willReturn([]);

        $connection->expects(self::exactly(33))
            ->method('insert')
            ->willReturnCallback(
                static function (string $query, array $bindings) use (
                    &$inserted
                ): bool {
                    $inserted[] = [$query, $bindings];

                    return true;
                }
            );

        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');

        (new SeedMexicoGeography($connection))->execute();

        self::assertSame(2, $countryQueries);
        self::assertCount(33, $inserted);

        self::assertStringContainsString(
            'institutional.countries',
            $inserted[0][0]
        );

        self::assertSame(
            [
                'MX',
                'MEX',
                '484',
                'México',
                'Mexicano',
                'Mexicana',
                true,
            ],
            $inserted[0][1]
        );
    }

    public function test_rejects_conflicting_alpha2_code(): void
    {
        $this->assertCountryConflict(
            (object) ['codigo_alpha2' => 'MX']
        );
    }

    public function test_rejects_conflicting_numeric_code(): void
    {
        $this->assertCountryConflict(
            (object) ['codigo_num3' => '484']
        );
    }

    public function test_rejects_incompatible_existing_mexico(): void
    {
        $connection = $this->administrativeConnection();

        $invalid = self::countryRecord(
            MexicoGeographyCatalog::country()
        );

        $invalid->codigo_num3 = '999';

        $connection->method('selectOne')
            ->willReturnCallback(
                static function (string $query) use ($invalid): ?object {
                    if (str_contains($query, 'session_user')) {
                        return self::administrativeIdentity();
                    }

                    if (str_contains($query, 'institutional.countries')) {
                        return $invalid;
                    }

                    throw new RuntimeException('Consulta inesperada.');
                }
            );

        $connection->expects(self::never())->method('insert');
        $connection->expects(self::never())->method('commit');
        $connection->expects(self::once())->method('rollBack');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('incompatible');

        (new SeedMexicoGeography($connection))->execute();
    }

    public function test_rolls_back_when_country_id_cannot_be_resolved(): void
    {
        $connection = $this->administrativeConnection();

        $connection->method('selectOne')
            ->willReturnCallback(
                static function (string $query): ?object {
                    if (str_contains($query, 'session_user')) {
                        return self::administrativeIdentity();
                    }

                    if (str_contains($query, 'institutional.countries')) {
                        return null;
                    }

                    throw new RuntimeException('Consulta inesperada.');
                }
            );

        $connection->expects(self::once())
            ->method('insert')
            ->with(
                self::stringContains('institutional.countries'),
                self::isArray()
            )
            ->willReturn(true);

        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::never())->method('commit');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('id_pais');

        (new SeedMexicoGeography($connection))->execute();
    }

    private function assertCountryConflict(object $conflict): void
    {
        $connection = $this->administrativeConnection();

        $connection->method('selectOne')
            ->willReturnCallback(
                static function (string $query) use ($conflict): ?object {
                    if (str_contains($query, 'session_user')) {
                        return self::administrativeIdentity();
                    }

                    if (str_contains($query, 'codigo_alpha2 = ?')) {
                        return $conflict;
                    }

                    if (str_contains($query, 'institutional.countries')) {
                        return null;
                    }

                    throw new RuntimeException('Consulta inesperada.');
                }
            );

        $connection->expects(self::never())->method('insert');
        $connection->expects(self::never())->method('commit');
        $connection->expects(self::once())->method('rollBack');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('conflicto');

        (new SeedMexicoGeography($connection))->execute();
    }

    private function administrativeConnection(): ConnectionInterface
    {
        $connection = $this->createMock(ConnectionInterface::class);

        $connection->method('transactionLevel')
            ->willReturnOnConsecutiveCalls(0, 1);

        $connection->expects(self::once())
            ->method('beginTransaction');

        $connection->method('statement')->willReturn(true);

        return $connection;
    }

    private static function administrativeIdentity(): object
    {
        return (object) [
            'login_role' => 'siga_migrator',
            'effective_role' => 'siga_migrator',
        ];
    }

    private static function countryRecord(array $country): object
    {
        return (object) [
            'id_pais' => self::COUNTRY_ID,
            'codigo_alpha2' => $country['codigo_alpha2'],
            'codigo_alpha3' => $country['codigo_alpha3'],
            'codigo_num3' => $country['codigo_num3'],
            'nombre' => $country['nombre'],
            'activo' => $country['activo'],
        ];
    }
}
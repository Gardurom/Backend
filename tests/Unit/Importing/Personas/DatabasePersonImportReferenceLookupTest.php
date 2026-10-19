<?php

namespace Tests\Unit\Importing\Personas;

use App\Importing\Personas\DatabasePersonImportReferenceLookup;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\TestCase;

class DatabasePersonImportReferenceLookupTest extends TestCase
{
    public function test_finds_active_country_id_by_alpha3(): void
    {
        $connection =
            $this->createMock(
                ConnectionInterface::class
            );

        $connection
            ->expects(self::once())
            ->method('selectOne')
            ->willReturnCallback(
                function (
                    string $query,
                    array $bindings
                ): object {
                    $normalizedQuery =
                        $this->normalizeSql(
                            $query
                        );

                    self::assertStringContainsString(
                        'FROM institutional.countries',
                        $normalizedQuery
                    );

                    self::assertStringContainsString(
                        'codigo_alpha3 = ?',
                        $normalizedQuery
                    );

                    self::assertStringContainsString(
                        'activo = true',
                        $normalizedQuery
                    );

                    self::assertSame(
                        ['MEX'],
                        $bindings
                    );

                    return (object) [
                        'id_pais' => 'pais-mex',
                    ];
                }
            );

        $lookup =
            new DatabasePersonImportReferenceLookup(
                $connection
            );

        self::assertSame(
            'pais-mex',
            $lookup
                ->findActiveCountryIdByAlpha3(
                    'MEX'
                )
        );
    }

    public function test_returns_null_when_active_country_does_not_exist(): void
    {
        $connection =
            $this->createMock(
                ConnectionInterface::class
            );

        $connection
            ->expects(self::once())
            ->method('selectOne')
            ->willReturn(null);

        $lookup =
            new DatabasePersonImportReferenceLookup(
                $connection
            );

        self::assertNull(
            $lookup
                ->findActiveCountryIdByAlpha3(
                    'ZZZ'
                )
        );
    }

    public function test_returns_all_active_territory_matches(): void
    {
        $connection =
            $this->createMock(
                ConnectionInterface::class
            );

        $connection
            ->expects(self::once())
            ->method('select')
            ->willReturnCallback(
                function (
                    string $query,
                    array $bindings
                ): array {
                    $normalizedQuery =
                        $this->normalizeSql(
                            $query
                        );

                    self::assertStringContainsString(
                        'FROM institutional.territories',
                        $normalizedQuery
                    );

                    self::assertStringContainsString(
                        'id_pais = ?',
                        $normalizedQuery
                    );

                    self::assertStringContainsString(
                        'clave_oficial = ?',
                        $normalizedQuery
                    );

                    self::assertStringContainsString(
                        'activo = true',
                        $normalizedQuery
                    );

                    self::assertSame(
                        [
                            'pais-mex',
                            '18',
                        ],
                        $bindings
                    );

                    return [
                        (object) [
                            'id_territorio' => 'territorio-uno',
                        ],
                        (object) [
                            'id_territorio' => 'territorio-dos',
                        ],
                    ];
                }
            );

        $lookup =
            new DatabasePersonImportReferenceLookup(
                $connection
            );

        self::assertSame(
            [
                'territorio-uno',
                'territorio-dos',
            ],
            $lookup->findActiveTerritoryIds(
                'pais-mex',
                '18'
            )
        );
    }

    public function test_returns_empty_list_when_territory_does_not_exist(): void
    {
        $connection =
            $this->createMock(
                ConnectionInterface::class
            );

        $connection
            ->expects(self::once())
            ->method('select')
            ->willReturn([]);

        $lookup =
            new DatabasePersonImportReferenceLookup(
                $connection
            );

        self::assertSame(
            [],
            $lookup->findActiveTerritoryIds(
                'pais-mex',
                '99'
            )
        );
    }

    private function normalizeSql(
        string $sql
    ): string {
        return preg_replace(
            '/\s+/',
            ' ',
            trim($sql)
        ) ?? $sql;
    }
}

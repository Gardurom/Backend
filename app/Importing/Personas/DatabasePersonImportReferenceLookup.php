<?php

namespace App\Importing\Personas;

use Illuminate\Database\ConnectionInterface;

final class DatabasePersonImportReferenceLookup implements PersonImportReferenceLookup
{
    public function __construct(
        private readonly ConnectionInterface $connection
    ) {}

    public function findActiveCountryIdByAlpha3(
        string $alpha3
    ): ?string {
        $country = $this->connection->selectOne(
            <<<'SQL'
            SELECT id_pais
            FROM institutional.countries
            WHERE codigo_alpha3 = ?
                AND activo = true
            SQL,
            [
                $alpha3,
            ]
        );

        if ($country === null) {
            return null;
        }

        return (string) $country->id_pais;
    }

    /**
     * @return list<string>
     */
    public function findActiveTerritoryIds(
        string $countryId,
        string $officialKey
    ): array {
        $territories = $this->connection->select(
            <<<'SQL'
            SELECT id_territorio
            FROM institutional.territories
            WHERE id_pais = ?
                AND clave_oficial = ?
                AND activo = true
            ORDER BY id_territorio
            SQL,
            [
                $countryId,
                $officialKey,
            ]
        );

        return array_map(
            static fn (object $territory): string => (string) $territory->id_territorio,
            $territories
        );
    }
}

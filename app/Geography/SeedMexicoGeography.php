<?php

namespace App\Geography;

use Illuminate\Database\ConnectionInterface;
use RuntimeException;
use Throwable;

final class SeedMexicoGeography implements MexicoGeographyLoader
{
    public function __construct(
        private readonly ConnectionInterface $connection
    ) {}

    public function execute(): void
    {
        $identity = $this->connection->selectOne(
            'SELECT
                session_user AS login_role,
                current_user AS effective_role'
        );

        if (
            $identity === null
            || $identity->login_role !== 'siga_migrator'
            || $identity->effective_role !== 'siga_migrator'
        ) {
            throw new RuntimeException(
                'La carga geográfica requiere una conexión administrativa siga_migrator.'
            );
        }

        if ($this->connection->transactionLevel() !== 0) {
            throw new RuntimeException(
                'La carga geográfica no permite una transacción previamente abierta.'
            );
        }

        $this->connection->beginTransaction();

        try {
            $this->connection->statement(
                'SET LOCAL ROLE siga_owner'
            );

            $this->connection->statement(
                'SELECT pg_advisory_xact_lock(314159, 271828)'
            );

            $country = MexicoGeographyCatalog::country();

            $existing = $this->connection->selectOne(
                'SELECT
                    id_pais,
                    codigo_alpha2,
                    codigo_alpha3,
                    codigo_num3,
                    nombre,
                    activo
                 FROM institutional.countries
                 WHERE codigo_alpha3 = ?',
                [$country['codigo_alpha3']]
            );

            if ($existing === null) {
                $conflict = $this->connection->selectOne(
                    'SELECT
                        id_pais,
                        codigo_alpha2,
                        codigo_alpha3,
                        codigo_num3
                     FROM institutional.countries
                     WHERE codigo_alpha2 = ?
                        OR codigo_num3 = ?',
                    [
                        $country['codigo_alpha2'],
                        $country['codigo_num3'],
                    ]
                );

                if ($conflict !== null) {
                    throw new RuntimeException(
                        'Se detectó un conflicto entre los códigos ISO de México y un país existente.'
                    );
                }

                $this->connection->insert(
                    'INSERT INTO institutional.countries (
                        codigo_alpha2,
                        codigo_alpha3,
                        codigo_num3,
                        nombre,
                        nacionalidad_masculina,
                        nacionalidad_femenina,
                        activo
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [
                        $country['codigo_alpha2'],
                        $country['codigo_alpha3'],
                        $country['codigo_num3'],
                        $country['nombre'],
                        $country['nacionalidad_masculina'],
                        $country['nacionalidad_femenina'],
                        $country['activo'],
                    ]
                );
            }

            $resolved = $existing ?? $this->connection->selectOne(
                'SELECT
                    id_pais,
                    codigo_alpha2,
                    codigo_alpha3,
                    codigo_num3,
                    nombre,
                    activo
                 FROM institutional.countries
                 WHERE codigo_alpha3 = ?',
                [$country['codigo_alpha3']]
            );

            if (
                $resolved === null
                || !isset($resolved->id_pais)
                || !is_string($resolved->id_pais)
                || $resolved->id_pais === ''
            ) {
                throw new RuntimeException(
                    'No se pudo resolver id_pais de México.'
                );
            }

            foreach ([
                'codigo_alpha2',
                'codigo_alpha3',
                'codigo_num3',
                'nombre',
                'activo',
            ] as $field) {
                if (
                    !property_exists($resolved, $field)
                    || $resolved->{$field} !== $country[$field]
                ) {
                    throw new RuntimeException(
                        'El registro existente de México es incompatible.'
                    );
                }
            }

            $territories = $this->connection->select(
                'SELECT
                    id_territorio,
                    id_pais,
                    id_territorio_padre,
                    tipo_territorio,
                    clave_oficial,
                    nombre,
                    activo
                 FROM institutional.territories
                 WHERE id_pais = ?
                   AND tipo_territorio = ?
                 ORDER BY clave_oficial',
                [
                    $resolved->id_pais,
                    'ENTIDAD_FEDERATIVA',
                ]
            );

            $expectedTerritories = [];

            foreach (MexicoGeographyCatalog::territories() as $territory) {
                $expectedTerritories[$territory['clave_oficial']] = $territory;
            }

            $existingKeys = [];

            foreach ($territories as $existingTerritory) {
                $key = $existingTerritory->clave_oficial ?? null;

                if (
                    !is_string($key)
                    || !array_key_exists($key, $expectedTerritories)
                ) {
                    throw new RuntimeException(
                        'El catálogo contiene una entidad federativa incompatible.'
                    );
                }

                if (in_array($key, $existingKeys, true)) {
                    throw new RuntimeException(
                        "La clave oficial {$key} está duplicada en el catálogo territorial."
                    );
                }

                $expected = $expectedTerritories[$key];

                foreach ([
                    'id_pais' => $resolved->id_pais,
                    'id_territorio_padre' => null,
                    'tipo_territorio' => $expected['tipo_territorio'],
                    'nombre' => $expected['nombre'],
                    'activo' => $expected['activo'],
                ] as $field => $value) {
                    if (
                        !property_exists($existingTerritory, $field)
                        || $existingTerritory->{$field} !== $value
                    ) {
                        throw new RuntimeException(
                            "La entidad federativa {$key} es incompatible: {$field}."
                        );
                    }
                }

                $existingKeys[] = $key;
            }

            foreach (MexicoGeographyCatalog::territories() as $territory) {
                if (in_array($territory['clave_oficial'], $existingKeys, true)) {
                    continue;
                }

                $this->connection->insert(
                        'INSERT INTO institutional.territories (
                            id_pais,
                            id_territorio_padre,
                            tipo_territorio,
                            clave_oficial,
                            nombre,
                            activo
                        ) VALUES (?, ?, ?, ?, ?, ?)',
                        [
                            $resolved->id_pais,
                            null,
                            $territory['tipo_territorio'],
                            $territory['clave_oficial'],
                            $territory['nombre'],
                            $territory['activo'],
                        ]
                    );
            }

            $this->connection->commit();
        } catch (Throwable $error) {
            if ($this->connection->transactionLevel() > 0) {
                $this->connection->rollBack();
            }

            throw $error;
        }
    }
}
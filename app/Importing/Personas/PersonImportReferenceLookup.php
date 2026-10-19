<?php

namespace App\Importing\Personas;

interface PersonImportReferenceLookup
{
    public function findActiveCountryIdByAlpha3(
        string $alpha3
    ): ?string;

    /**
     * Devuelve todos los territorios activos que coinciden
     * con país y clave oficial.
     *
     * Más de un resultado representa una referencia ambigua.
     *
     * @return list<string>
     */
    public function findActiveTerritoryIds(
        string $countryId,
        string $officialKey
    ): array;
}

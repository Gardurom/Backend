<?php

namespace App\Importing\Personas;

final class PersonImportReferenceResolver
{
    /**
     * @var array<string, string|null>
     */
    private array $countryCache = [];

    /**
     * @var array<string, list<string>>
     */
    private array $territoryCache = [];

    public function __construct(
        private readonly PersonImportReferenceLookup $lookup
    ) {}

    /**
     * Recibe referencias ya normalizadas y validadas sintácticamente.
     *
     * @param  array<string, mixed>  $normalizedData
     */
    public function resolve(
        array $normalizedData
    ): PersonImportReferenceResolutionResult {
        $resolvedData = [
            'id_pais_origen' => null,
            'id_pais_nacimiento' => null,
            'id_territorio_nacimiento' => null,
        ];

        $errors = [];

        $originAlpha3 =
            $normalizedData['pais_origen_alpha3']
            ?? null;

        if ($originAlpha3 !== null) {
            $originCountryId =
                $this->resolveCountryId(
                    $originAlpha3
                );

            if ($originCountryId === null) {
                $errors['pais_origen_alpha3'][] =
                    'El país de origen no existe o no está activo.';
            } else {
                $resolvedData['id_pais_origen'] =
                    $originCountryId;
            }
        }

        $birthAlpha3 =
            $normalizedData['pais_nacimiento_alpha3']
            ?? null;

        $territoryKey =
            $normalizedData['territorio_nacimiento_clave']
            ?? null;

        $birthCountryId = null;

        if ($birthAlpha3 !== null) {
            $birthCountryId =
                $this->resolveCountryId(
                    $birthAlpha3
                );

            if ($birthCountryId === null) {
                $errors['pais_nacimiento_alpha3'][] =
                    'El país de nacimiento no existe o no está activo.';
            } else {
                $resolvedData['id_pais_nacimiento'] =
                    $birthCountryId;
            }
        }

        if (
            $territoryKey !== null
            && $birthCountryId !== null
        ) {
            $territoryIds =
                $this->resolveTerritoryIds(
                    $birthCountryId,
                    $territoryKey
                );

            if ($territoryIds === []) {
                $errors['territorio_nacimiento_clave'][] =
                    'El territorio de nacimiento no existe o no está activo.';
            } elseif (count($territoryIds) > 1) {
                $errors['territorio_nacimiento_clave'][] =
                    'La clave territorial es ambigua para el país de nacimiento.';
            } else {
                $resolvedData['id_territorio_nacimiento'] =
                    $territoryIds[0];
            }
        }

        return new PersonImportReferenceResolutionResult(
            resolvedData: $resolvedData,
            errors: $errors
        );
    }

    private function resolveCountryId(
        string $alpha3
    ): ?string {
        if (
            array_key_exists(
                $alpha3,
                $this->countryCache
            )
        ) {
            return $this->countryCache[$alpha3];
        }

        $this->countryCache[$alpha3] =
            $this->lookup
                ->findActiveCountryIdByAlpha3(
                    $alpha3
                );

        return $this->countryCache[$alpha3];
    }

    /**
     * @return list<string>
     */
    private function resolveTerritoryIds(
        string $countryId,
        string $officialKey
    ): array {
        $cacheKey =
            $countryId.'|'.$officialKey;

        if (
            array_key_exists(
                $cacheKey,
                $this->territoryCache
            )
        ) {
            return $this->territoryCache[$cacheKey];
        }

        $this->territoryCache[$cacheKey] =
            $this->lookup
                ->findActiveTerritoryIds(
                    $countryId,
                    $officialKey
                );

        return $this->territoryCache[$cacheKey];
    }
}

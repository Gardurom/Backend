<?php

namespace Tests\Installation;

class ProfileCatalogTest extends InstallationTestCase
{
    public function test_initial_profiles_catalog_exists(): void
    {
        $expected = [
            'PERFIL_ADMINISTRACION' => 'Administración',
            'PERFIL_AUDITORIA' => 'Auditoría',
            'PERFIL_PERSONAS' => 'Personas',
        ];

        $profiles = $this->db->select(
            "SELECT code, name
             FROM system.profiles
             WHERE code = ANY (ARRAY[
                 'PERFIL_ADMINISTRACION',
                 'PERFIL_AUDITORIA',
                 'PERFIL_PERSONAS'
             ])
             ORDER BY code"
        );

        $actual = [];

        foreach ($profiles as $profile) {
            $actual[$profile->code] = $profile->name;
        }

        self::assertSame(
            $expected,
            $actual,
            'El catálogo inicial de perfiles de SIGA no coincide con lo esperado.'
        );
    }

    public function test_initial_profile_codes_are_unique(): void
    {
        $duplicates = $this->db->select(
            "SELECT code, COUNT(*) AS total
             FROM system.profiles
             WHERE code = ANY (ARRAY[
                 'PERFIL_ADMINISTRACION',
                 'PERFIL_AUDITORIA',
                 'PERFIL_PERSONAS'
             ])
             GROUP BY code
             HAVING COUNT(*) > 1"
        );

        self::assertSame(
            [],
            $duplicates,
            'Los códigos del catálogo inicial de perfiles deben ser únicos.'
        );
    }
}

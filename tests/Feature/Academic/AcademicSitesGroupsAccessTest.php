<?php

namespace Tests\Feature\Academic;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AcademicSitesGroupsAccessTest extends TestCase
{
    #[DataProvider('registrationEndpoints')]
    public function test_unauthenticated_registration_is_rejected(
        string $uri,
        array $payload
    ): void {
        $this->postJson($uri, $payload)->assertUnauthorized();
    }

    public static function registrationEndpoints(): array
    {
        return [
            'registro sede' => [
                '/api/academico/sedes',
                [
                    'codigo' => 'SEDEVALIDACION',
                    'nombre' => 'Sede de validacion de acceso',
                ],
            ],
            'registro grupo' => [
                '/api/academico/grupos',
                [
                    'id_cohorte' => '00000000-0000-4000-8000-000000000001',
                    'codigo' => 'GRUPOVALIDACION',
                    'especializacion' => 'CAMPO',
                ],
            ],
        ];
    }
}
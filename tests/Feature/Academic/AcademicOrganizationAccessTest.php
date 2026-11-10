<?php

namespace Tests\Feature\Academic;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AcademicOrganizationAccessTest extends TestCase
{
    #[DataProvider('protectedEndpoints')]
    public function test_unauthenticated_requests_are_rejected(
        string $uri
    ): void {
        $this->getJson($uri)->assertUnauthorized();
    }

    public static function protectedEndpoints(): array
    {
        return [
            'programas' => ['/api/academico/programas'],
            'cohortes' => ['/api/academico/cohortes'],
            'sedes' => ['/api/academico/sedes'],
            'grupos' => ['/api/academico/grupos'],
            'historial' => [
                '/api/academico/historial-sedes'
                .'?tipo=COHORTE'
                .'&id=00000000-0000-4000-8000-000000000001',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('registrationEndpoints')]
    public function test_unauthenticated_registration_is_rejected(
        string $uri,
        array $payload
    ): void {
        $this->postJson($uri, $payload)->assertUnauthorized();
    }

    public static function registrationEndpoints(): array
    {
        return [
            'programas POST' => [
                '/api/academico/programas',
                [
                    'codigo' => 'VALIDACION',
                    'nombre' => 'Validacion de acceso',
                ],
            ],
            'cohortes POST' => [
                '/api/academico/cohortes',
                [
                    'id_programa' => '00000000-0000-4000-8000-000000000001',
                    'tipo' => 'G',
                    'numero' => 1,
                    'anio' => 2026,
                ],
            ],
        ];
    }
}
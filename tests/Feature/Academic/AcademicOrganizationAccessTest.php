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
}
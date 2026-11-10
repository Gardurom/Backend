<?php

namespace Tests\Feature\Academic;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AcademicSiteAssignmentAccessTest extends TestCase
{
    #[DataProvider('endpoints')]
    public function test_anonymous_site_assignment_is_rejected(
        string $uri,
        array $data
    ): void {
        $this->postJson($uri, $data)->assertUnauthorized();
    }

    public static function endpoints(): array
    {
        return [
            'cohorte' => [
                '/api/academico/cohortes/adscripciones',
                [
                    'id_cohorte' => '00000000-0000-4000-8000-000000000001',
                    'id_sede' => '00000000-0000-4000-8000-000000000002',
                ],
            ],
            'grupo' => [
                '/api/academico/grupos/adscripciones',
                [
                    'id_grupo' => '00000000-0000-4000-8000-000000000003',
                    'id_sede' => '00000000-0000-4000-8000-000000000002',
                ],
            ],
        ];
    }
}
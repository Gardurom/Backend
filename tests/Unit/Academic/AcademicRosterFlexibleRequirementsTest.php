<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use App\Importing\Academic\AcademicRosterColumnMapper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AcademicRosterFlexibleRequirementsTest extends TestCase
{
    private function mapper(): AcademicRosterColumnMapper
    {
        return new AcademicRosterColumnMapper();
    }

    public function test_fifth_generation_without_matricula(): void
    {
        $manifest = $this->mapper()->resolve([
            'NOMBRE COMPLETO',
            'GRUPO',
        ]);

        self::assertSame('FLEX_V1', $manifest['profile']);

        $row = $this->mapper()->mapRow([
            'NOMBRE COMPLETO' => 'PERSONA DE EJEMPLO',
            'GRUPO' => 'A',
        ], $manifest);

        self::assertTrue($row['matricula_pendiente']);
        self::assertArrayNotHasKey('matricula', $row['canonical']);
    }

    public function test_mandos_ansp_are_external(): void
    {
        $manifest = $this->mapper()->resolve([
            'MATRÍCULA ANSP',
            'NOMBRE COMPLETO',
            'JERARQUÍA',
        ]);

        $row = $this->mapper()->mapRow([
            'MATRÍCULA ANSP' => 'ANSP-001',
            'NOMBRE COMPLETO' => 'PARTICIPANTE EJEMPLO',
            'JERARQUÍA' => 'REFERENCIA',
        ], $manifest);

        self::assertSame(
            'ANSP-001',
            $row['canonical']['matricula_externa_ansp']
        );

        self::assertTrue($row['matricula_pendiente']);

        self::assertSame(
            ['JERARQUÍA' => 'REFERENCIA'],
            $row['unmapped']
        );
    }

    public function test_especialidad_unrc_is_external(): void
    {
        $manifest = $this->mapper()->resolve([
            'MATRÍCULA UNRC',
            'NOMBRE COMPLETO',
        ]);

        $row = $this->mapper()->mapRow([
            'MATRÍCULA UNRC' => 'UNRC-002',
            'NOMBRE COMPLETO' => 'PARTICIPANTE EJEMPLO',
        ], $manifest);

        self::assertSame(
            'UNRC-002',
            $row['canonical']['matricula_externa_unrc']
        );

        self::assertTrue($row['matricula_pendiente']);
        self::assertArrayNotHasKey('matricula', $row['canonical']);
    }

    public function test_seventh_generation_without_matricula(): void
    {
        $manifest = $this->mapper()->resolve([
            'NO.',
            'GENERACIÓN/ GRUPO',
            'APELLIDO PATERNO',
            'NOMBRE (S)',
        ]);

        self::assertSame('7A', $manifest['profile']);

        $row = $this->mapper()->mapRow([
            'NO.' => 1,
            'GENERACIÓN/ GRUPO' => '7A/G01',
            'APELLIDO PATERNO' => 'PRUEBA',
            'NOMBRE (S)' => 'PERSONA',
        ], $manifest);

        self::assertTrue($row['matricula_pendiente']);
    }

    public function test_eighth_generation_without_matricula(): void
    {
        $manifest = $this->mapper()->resolve([
            'No.',
            'RFC',
            'NOMBRE COMPLETO',
            'GENERACIÓN',
            'GRUPO',
            'Gpo. No.',
        ]);

        self::assertSame('8A', $manifest['profile']);

        $row = $this->mapper()->mapRow([
            'No.' => 1,
            'RFC' => '',
            'NOMBRE COMPLETO' => 'APELLIDOS NOMBRES',
            'GENERACIÓN' => '8VA',
            'GRUPO' => 'A',
            'Gpo. No.' => 1,
        ], $manifest);

        self::assertTrue($row['matricula_pendiente']);
        self::assertArrayNotHasKey(
            'apellido_paterno',
            $row['canonical']
        );
    }

    public function test_rfc_curp_and_matricula_are_optional(): void
    {
        $manifest = $this->mapper()->resolve(['NOMBRES']);

        self::assertSame('FLEX_V1', $manifest['profile']);

        $row = $this->mapper()->mapRow(
            ['NOMBRES' => 'EJEMPLO'],
            $manifest
        );

        self::assertTrue($row['matricula_pendiente']);
        self::assertArrayNotHasKey('rfc', $row['canonical']);
        self::assertArrayNotHasKey('curp', $row['canonical']);
    }

    public function test_institutional_matricula_is_preserved(): void
    {
        $manifest = $this->mapper()->resolve([
            'MATRÍCULA',
            'NOMBRES',
        ]);

        $row = $this->mapper()->mapRow([
            'MATRÍCULA' => '001-ABC',
            'NOMBRES' => 'EJEMPLO',
        ], $manifest);

        self::assertFalse($row['matricula_pendiente']);
        self::assertSame(
            '001-ABC',
            $row['canonical']['matricula']
        );
    }

    public function test_flexible_custom_name_mapping(): void
    {
        $manifest = $this->mapper()->resolve(
            ['IDENTIDAD FUENTE', 'OTRO DATO'],
            'FLEX_V1',
            [
                'IDENTIDAD FUENTE' =>
                    'nombre_completo_fuente',
            ]
        );

        $row = $this->mapper()->mapRow([
            'IDENTIDAD FUENTE' => 'EJEMPLO',
            'OTRO DATO' => 'CONSERVAR',
        ], $manifest);

        self::assertSame(
            ['OTRO DATO' => 'CONSERVAR'],
            $row['unmapped']
        );

        self::assertTrue($row['matricula_pendiente']);
    }

    public function test_missing_nominal_field_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->mapper()->resolve([
            'MATRÍCULA ANSP',
            'RFC',
            'GRUPO',
        ]);
    }

    public function test_name_is_not_split_automatically(): void
    {
        $manifest = $this->mapper()->resolve([
            'NOMBRE',
        ]);

        $row = $this->mapper()->mapRow([
            'NOMBRE' => 'APELLIDOS COMPUESTOS Y NOMBRES',
        ], $manifest);

        self::assertSame(
            'APELLIDOS COMPUESTOS Y NOMBRES',
            $row['canonical']['nombre_completo_fuente']
        );

        self::assertArrayNotHasKey(
            'apellido_paterno',
            $row['canonical']
        );

        self::assertArrayNotHasKey(
            'apellido_materno',
            $row['canonical']
        );
    }
}
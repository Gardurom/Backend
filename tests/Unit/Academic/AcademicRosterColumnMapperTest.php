<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use App\Importing\Academic\AcademicRosterColumnMapper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AcademicRosterColumnMapperTest extends TestCase
{
    private function headers7(): array
    {
        return [
            'NO.',
            'MATRÍCULA',
            'GENERACIÓN/ GRUPO',
            'APELLIDO PATERNO',
            "APELLIDO\nMATERNO",
            'NOMBRE (S)',
            'EDAD',
            'RFC/HOMOCLAVE',
            'CURP',
            'SEXO',
            'LICENCIATURA',
            'TELÉFONO CELULAR CONTACTO',
            'CORREO ELECTRÓNICO CONTACTO',
        ];
    }

    private function headers8(): array
    {
        return [
            'No.',
            'ID BECARIO',
            'RFC',
            'NOMBRE COMPLETO',
            'GENERACIÓN',
            'GRUPO',
            'Gpo. No.',
        ];
    }

    public function test_detects_seventh_generation(): void
    {
        $manifest = (new AcademicRosterColumnMapper)
            ->resolve($this->headers7());

        self::assertSame('7A', $manifest['profile']);
        self::assertSame('1.0', $manifest['version']);

        self::assertSame(
            'MATRÍCULA',
            $manifest['map']['matricula']
        );

        self::assertSame(
            "APELLIDO\nMATERNO",
            $manifest['map']['apellido_materno']
        );

        self::assertSame(
            'RFC/HOMOCLAVE',
            $manifest['map']['rfc']
        );

        self::assertSame(
            'NO.',
            $manifest['map']['numero_lista_grupo']
        );
    }

    public function test_detects_eighth_generation(): void
    {
        $manifest = (new AcademicRosterColumnMapper)
            ->resolve($this->headers8());

        self::assertSame('8A', $manifest['profile']);

        self::assertSame(
            'No.',
            $manifest['map']['numero_fila_fuente']
        );

        self::assertSame(
            'Gpo. No.',
            $manifest['map']['numero_lista_grupo']
        );

        self::assertSame(
            'NOMBRE COMPLETO',
            $manifest['map']['nombre_completo_fuente']
        );
    }

    public function test_preserves_original_values(): void
    {
        $mapper = new AcademicRosterColumnMapper;
        $headers = $this->headers8();

        $source = array_combine(
            $headers,
            [
                1,
                'M-0001',
                'ABCD000101XYZ',
                'DOS APELLIDOS NOMBRES',
                '8va',
                1,
                2,
            ]
        );

        $result = $mapper->mapRow(
            $source,
            $mapper->resolve($headers)
        );

        self::assertSame(
            $source,
            $result['original']
        );

        self::assertSame(
            'DOS APELLIDOS NOMBRES',
            $result['canonical']['nombre_completo_fuente']
        );

        self::assertArrayNotHasKey(
            'apellido_paterno',
            $result['canonical']
        );

        self::assertSame(
            'M-0001',
            $result['canonical']['matricula']
        );
    }

    public function test_preserves_unknown_columns(): void
    {
        $mapper = new AcademicRosterColumnMapper;

        $manifest = $mapper->resolve([
            'MATRICULA',
            'NOMBRES',
            'OTRO DATO',
        ]);

        $result = $mapper->mapRow(
            [
                'MATRICULA' => 'M01',
                'NOMBRES' => 'EJEMPLO',
                'OTRO DATO' => 17,
            ],
            $manifest
        );

        self::assertSame(
            'FLEX_V1',
            $manifest['profile']
        );

        self::assertSame(
            ['OTRO DATO'],
            $manifest['unmapped']
        );

        self::assertSame(
            ['OTRO DATO' => 17],
            $result['unmapped']
        );
    }

    public function test_accepts_safe_custom_aliases(): void
    {
        $mapper = new AcademicRosterColumnMapper;

        $manifest = $mapper->resolve(
            ['IDENTIFICADOR', 'NOMBRES', 'SEDE ACAD'],
            'FLEX_V1',
            [
                'IDENTIFICADOR' => 'matricula',
                'SEDE ACAD' => 'sede_codigo',
            ]
        );

        self::assertSame(
            'IDENTIFICADOR',
            $manifest['map']['matricula']
        );

        self::assertSame(
            'SEDE ACAD',
            $manifest['map']['sede_codigo']
        );
    }

    public function test_rejects_ambiguous_aliases(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        (new AcademicRosterColumnMapper)->resolve([
            'MATRÍCULA',
            'ID BECARIO',
            'NOMBRES',
        ]);
    }

    public function test_rejects_duplicate_headers(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        (new AcademicRosterColumnMapper)->resolve([
            'MATRÍCULA',
            'matricula',
            'NOMBRES',
        ]);
    }

    public function test_rejects_missing_identity(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        (new AcademicRosterColumnMapper)->resolve([
            'MATRICULA',
            'RFC',
        ]);
    }

    public function test_rejects_contradictory_mapping(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        (new AcademicRosterColumnMapper)->resolve(
            ['MATRICULA', 'NOMBRES'],
            'FLEX_V1',
            ['MATRICULA' => 'rfc']
        );
    }

    public function test_rejects_missing_row_column(): void
    {
        $mapper = new AcademicRosterColumnMapper;

        $manifest = $mapper->resolve([
            'MATRICULA',
            'NOMBRES',
        ]);

        $this->expectException(
            InvalidArgumentException::class
        );

        $mapper->mapRow(
            ['MATRICULA' => 'M-001'],
            $manifest
        );
    }
}
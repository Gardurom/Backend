<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use App\Importing\Academic\AcademicFlexibleRosterXlsxReader;
use App\Importing\Academic\AcademicRosterColumnMapper;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\TestCase;

final class AcademicFlexibleRosterXlsxReaderTest extends TestCase
{
    private function reader(): AcademicFlexibleRosterXlsxReader
    {
        return new AcademicFlexibleRosterXlsxReader(
            new AcademicRosterColumnMapper()
        );
    }

    private function workbook(array $sheets): string
    {
        $path = tempnam(
            sys_get_temp_dir(),
            'siga_acad_'
        );

        if ($path === false) {
            throw new \RuntimeException(
                'No se pudo crear archivo temporal.'
            );
        }

        unlink($path);
        $path .= '.xlsx';

        $writer = new Writer();
        $writer->openToFile($path);

        try {
            foreach ($sheets as $index => $sheet) {
                $tab = $index === 0
                    ? $writer->getCurrentSheet()
                    : $writer->addNewSheetAndMakeItCurrent();

                $tab->setName($sheet['name']);

                foreach ($sheet['rows'] as $row) {
                    $writer->addRow(
                        Row::fromValues($row)
                    );
                }
            }
        } finally {
            $writer->close();
        }

        return $path;
    }

    private function headers7(string $rfc = 'RFC/HOMOCLAVE'): array
    {
        return [
            'NO.',
            'MATRÍCULA',
            'GENERACIÓN/ GRUPO',
            'APELLIDO PATERNO',
            "APELLIDO\nMATERNO",
            'NOMBRE (S)',
            'EDAD',
            $rfc,
            'CURP',
            'SEXO',
            'LICENCIATURA',
            'TELÉFONO CELULAR CONTACTO',
            'CORREO ELECTRÓNICO CONTACTO',
        ];
    }

    private function seven(int $number): array
    {
        return [
            $number,
            'MAT-'.str_pad((string)$number, 4, '0', STR_PAD_LEFT),
            '7A/G01',
            'PRUEBA',
            'EJEMPLO',
            'PERSONA',
            22,
            'PEPP000101ABC',
            'PEPP000101HNTRRS01',
            'MASCULINO',
            'LICENCIATURA',
            '0000000000',
            'test@example.invalid',
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

    public function test_reads_multiple_seventh_generation_sheets(): void
    {
        $path = $this->workbook([
            [
                'name' => 'GRUPO 01',
                'rows' => [
                    $this->headers7(),
                    $this->seven(1),
                    $this->seven(2),
                ],
            ],
            [
                'name' => 'GRUPO 02',
                'rows' => [
                    $this->headers7('RFC'),
                    $this->seven(3),
                ],
            ],
        ]);

        try {
            $items = iterator_to_array(
                $this->reader()->read($path),
                false
            );

            self::assertCount(3, $items);
            self::assertSame('7A', $items[0]['profile']);
            self::assertSame('GRUPO 02', $items[2]['sheet']);

            self::assertSame(
                'MAT-0003',
                $items[2]['canonical']['matricula']
            );

            self::assertSame(
                'PRUEBA',
                $items[0]['canonical']['apellido_paterno']
            );

            $summary = $this->reader()->inspect($path);

            self::assertSame(3, $summary['rows']);
            self::assertSame(2, $summary['sheets']);
            self::assertSame(0, $summary['identity_review']);
            self::assertSame(0, $summary['writes']);
        } finally {
            @unlink($path);
        }
    }

    public function test_eighth_generation_preserves_unsplit_name(): void
    {
        $path = $this->workbook([
            [
                'name' => '8A',
                'rows' => [
                    $this->headers8(),
                    [
                        1,
                        'B-0001',
                        'PEPP000101ABC',
                        'APELLIDOS NOMBRE',
                        '8VA',
                        1,
                        1,
                    ],
                    [
                        2,
                        'B-0002',
                        'PEPP000101AB',
                        'APELLIDOS DOS',
                        '8VA',
                        1,
                        2,
                    ],
                ],
            ],
        ]);

        try {
            $items = iterator_to_array(
                $this->reader()->read($path),
                false
            );

            self::assertCount(2, $items);
            self::assertSame('8A', $items[0]['profile']);

            self::assertSame(
                'APELLIDOS NOMBRE',
                $items[0]['original']['NOMBRE COMPLETO']
            );

            self::assertArrayNotHasKey(
                'apellido_paterno',
                $items[0]['canonical']
            );

            self::assertSame(
                2,
                $items[1]['canonical']['numero_lista_grupo']
            );

            $summary = $this->reader()->inspect($path);

            self::assertSame(
                2,
                $summary['identity_review']
            );

            self::assertSame(
                1,
                $summary['rfc_format_incidents']
            );

            self::assertSame(1, $summary['sheets']);
        } finally {
            @unlink($path);
        }
    }

    public function test_flexible_mapping_retains_unknown_columns(): void
    {
        $path = $this->workbook([
            [
                'name' => 'FUTURO',
                'rows' => [
                    [
                        'FOLIO FUENTE',
                        'NOMBRE DE FUENTE',
                        'NUEVO CAMPO',
                    ],
                    [
                        'F-01',
                        'PRUEBA FUTURA',
                        'EXTRA',
                    ],
                ],
            ],
        ]);

        try {
            $overrides = [
                'FOLIO FUENTE' => 'matricula',
                'NOMBRE DE FUENTE' => 'nombre_completo_fuente',
            ];

            $items = iterator_to_array(
                $this->reader()->read(
                    $path,
                    'FLEX_V1',
                    $overrides
                ),
                false
            );

            self::assertSame(
                'FLEX_V1',
                $items[0]['profile']
            );

            self::assertSame(
                'F-01',
                $items[0]['canonical']['matricula']
            );

            self::assertSame(
                ['NUEVO CAMPO' => 'EXTRA'],
                $items[0]['unmapped']
            );

            $summary = $this->reader()->inspect(
                $path,
                'FLEX_V1',
                $overrides
            );

            self::assertSame(
                1,
                $summary['per_sheet'][0]['unmapped_headers']
            );

            self::assertSame(
                1,
                $summary['identity_review']
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_preserves_extra_columns_in_data_rows(): void
    {
        $path = $this->workbook([
            [
                'name' => '8A',
                'rows' => [
                    $this->headers8(),
                    [
                        1,
                        'B-0001',
                        'PEPP000101ABC',
                        'APELLIDOS NOMBRE',
                        '8VA',
                        1,
                        1,
                        'NO DECLARADA',
                    ],
                ],
            ],
        ]);

        try {
            $items = iterator_to_array(
                $this->reader()->read($path),
                false
            );

            self::assertCount(1, $items);

            self::assertSame(
                'NO DECLARADA',
                $items[0]['unmapped']['columna_8_sin_encabezado']
            );

            self::assertSame(
                'NO DECLARADA',
                $items[0]['original']['columna_8_sin_encabezado']
            );

            self::assertSame(
                'B-0001',
                $items[0]['canonical']['matricula']
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_rejects_duplicate_headers(): void
    {
        $path = $this->workbook([
            [
                'name' => 'FUTURO',
                'rows' => [
                    [
                        'MATRÍCULA',
                        'matricula',
                        'NOMBRES',
                    ],
                    [
                        'M001',
                        'M001',
                        'PRUEBA',
                    ],
                ],
            ],
        ]);

        try {
            $this->expectException(
                InvalidArgumentException::class
            );

            iterator_to_array(
                $this->reader()->read($path),
                false
            );
        } finally {
            @unlink($path);
        }
    }
}
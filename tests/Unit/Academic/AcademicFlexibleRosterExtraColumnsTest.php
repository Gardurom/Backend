<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use App\Importing\Academic\AcademicFlexibleRosterXlsxReader;
use App\Importing\Academic\AcademicRosterColumnMapper;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\TestCase;

final class AcademicFlexibleRosterExtraColumnsTest extends TestCase
{
    private function reader(): AcademicFlexibleRosterXlsxReader
    {
        return new AcademicFlexibleRosterXlsxReader(
            new AcademicRosterColumnMapper()
        );
    }

    private function workbook(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'siga_extra_');

        if ($path === false) {
            throw new \RuntimeException('Temporal no disponible.');
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

                foreach ($sheet['rows'] as $values) {
                    $writer->addRow(Row::fromValues($values));
                }
            }
        } finally {
            $writer->close();
        }

        return $path;
    }

    public function test_fifth_generation_preserves_extra_data(): void
    {
        $path = $this->workbook([
            [
                'name' => 'GRUPO 1',
                'rows' => [
                    [null, 'GRUPO 1 - 5TA'],
                    [null, null],
                    [1, 'PERSONA EJEMPLO', 'OBSERVACION'],
                    [2, 'OTRA PERSONA'],
                ],
            ],
            [
                'name' => 'Hoja4',
                'rows' => [
                    ['#REF!', 'HOJA AUXILIAR'],
                ],
            ],
        ]);

        try {
            $items = iterator_to_array(
                $this->reader()->read(
                    $path,
                    null,
                    [],
                    '5A_GROUP_LIST'
                ),
                false
            );

            self::assertCount(2, $items);

            self::assertSame(
                'OBSERVACION',
                $items[0]['unmapped']['columna_3_sin_encabezado']
            );

            self::assertSame(
                'OBSERVACION',
                $items[0]['original']['columna_3_sin_encabezado']
            );

            self::assertSame([], $items[1]['unmapped']);
        } finally {
            @unlink($path);
        }
    }

    public function test_mandos_preserves_unheaded_fourth_column(): void
    {
        $path = $this->workbook([
            [
                'name' => 'Hoja1',
                'rows' => [
                    ['DIPLOMADO DE MANDOS'],
                    ['GRUPO 1'],
                    ['LISTADO'],
                    [],
                    ['NO.', 'MATRÍCULA ANSP', 'NOMBRE'],
                    [1, 'ANSP-001', 'PERSONA EJEMPLO', 'NOTA'],
                    [2, 'ANSP-002', 'OTRA PERSONA'],
                ],
            ],
        ]);

        try {
            $items = iterator_to_array(
                $this->reader()->read($path),
                false
            );

            self::assertCount(2, $items);

            self::assertSame(
                'NOTA',
                $items[0]['unmapped']['columna_4_sin_encabezado']
            );

            self::assertSame(
                'NOTA',
                $items[0]['original']['columna_4_sin_encabezado']
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_fifth_generation_still_requires_name(): void
    {
        $path = $this->workbook([
            [
                'name' => 'GRUPO 1',
                'rows' => [
                    [null, 'GRUPO 1 - 5TA'],
                    [null, null],
                    [1, null, 'OBSERVACION'],
                ],
            ],
        ]);

        try {
            $this->expectException(
                InvalidArgumentException::class
            );

            iterator_to_array(
                $this->reader()->read(
                    $path,
                    null,
                    [],
                    '5A_GROUP_LIST'
                ),
                false
            );
        } finally {
            @unlink($path);
        }
    }
}
<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use App\Importing\Academic\AcademicFlexibleRosterXlsxReader;
use App\Importing\Academic\AcademicRosterColumnMapper;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\TestCase;

final class AcademicRosterVacantSlotsTest extends TestCase
{
    private function reader(): AcademicFlexibleRosterXlsxReader
    {
        return new AcademicFlexibleRosterXlsxReader(
            new AcademicRosterColumnMapper()
        );
    }

    private function workbook(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'siga_slots_');

        if ($path === false) {
            throw new \RuntimeException(
                'No fue posible crear un archivo temporal.'
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

                foreach ($sheet['rows'] as $values) {
                    $writer->addRow(
                        Row::fromValues($values)
                    );
                }
            }
        } finally {
            $writer->close();
        }

        return $path;
    }

    public function test_numbered_blank_slots_are_counted(): void
    {
        $path = $this->workbook([
            [
                'name' => 'GRUPO 4',
                'rows' => [
                    [null, 'GRUPO 4 - 5TA'],
                    [null, null],
                    [1, 'PERSONA EJEMPLO', 'ANOTACION'],
                    [2, 'OTRA PERSONA'],
                    [3, null, null],
                    [4, null, null],
                    [5, null, null],
                ],
            ],
            [
                'name' => 'Hoja4',
                'rows' => [
                    ['#REF!', 'AUXILIAR'],
                ],
            ],
        ]);

        try {
            $report = $this->reader()->inspect(
                $path,
                null,
                [],
                '5A_GROUP_LIST'
            );

            self::assertSame(2, $report['rows']);
            self::assertSame(1, $report['sheets']);
            self::assertSame(3, $report['blank_numbered_slots']);

            self::assertSame(
                1,
                $report['per_sheet'][0]['unmapped_headers']
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_blank_slots_do_not_generate_participants(): void
    {
        $path = $this->workbook([
            [
                'name' => 'GRUPO 1',
                'rows' => [
                    [null, 'GRUPO 1 - 5TA'],
                    [null, null],
                    [1, 'PERSONA A'],
                    [2, null, null],
                    [3, 'PERSONA B'],
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

            self::assertSame(3, $items[0]['row_number']);
            self::assertSame(5, $items[1]['row_number']);

            self::assertSame(
                'PERSONA B',
                $items[1]['canonical']['nombre_completo_fuente']
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_missing_name_with_other_data_is_rejected(): void
    {
        $path = $this->workbook([
            [
                'name' => 'GRUPO 1',
                'rows' => [
                    [null, 'GRUPO 1 - 5TA'],
                    [null, null],
                    [1, null, 'DATO SIN PERSONA'],
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
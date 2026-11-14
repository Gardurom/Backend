<?php
declare(strict_types=1);

namespace Tests\Unit\Academic;

use App\Importing\Academic\AcademicFlexibleRosterXlsxReader;
use App\Importing\Academic\AcademicRosterColumnMapper;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\TestCase;

final class AcademicFlexibleRosterVariableHeadersTest extends TestCase
{
    private function sample(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'siga_f2_');

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

                foreach ($sheet['rows'] as $row) {
                    $writer->addRow(Row::fromValues($row));
                }
            }
        } finally {
            $writer->close();
        }

        return $path;
    }

    private function reader(): AcademicFlexibleRosterXlsxReader
    {
        return new AcademicFlexibleRosterXlsxReader(
            new AcademicRosterColumnMapper()
        );
    }

    public function test_mandos_header_on_fifth_row(): void
    {
        $path = $this->sample([[
            'name' => 'MANDOS',
            'rows' => [
                ['Diplomado de Mandos'],
                ['Grupo 1'],
                ['Listado de participantes'],
                [null, null, null],
                ['No.', 'Matrícula ANSP', 'Nombre'],
                [1, 'ANSP-01', 'APELLIDOS NOMBRE'],
            ],
        ]]);

        try {
            $rows = iterator_to_array($this->reader()->read($path), false);
            self::assertCount(1, $rows);
            self::assertSame(6, $rows[0]['row_number']);
            self::assertSame(
                'ANSP-01',
                $rows[0]['canonical']['matricula_externa_ansp']
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_empty_header_cell_is_preserved(): void
    {
        $path = $this->sample([[
            'name' => 'LISTADO',
            'rows' => [
                [null, null, null, 'Mandos'],
                [null, null, null, null],
                [null, 'Sector', 'Cargo', 'No.', 'Matrícula', 'RFC', 'Nombre'],
                [23, 'SECTOR 23', 'JEFE', 1, 'MAT-01', null, 'APELLIDOS NOMBRE'],
            ],
        ]]);

        try {
            $rows = iterator_to_array($this->reader()->read($path), false);
            self::assertCount(1, $rows);
            self::assertSame(
                23,
                $rows[0]['unmapped']['__SIN_ENCABEZADO_1']
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_editorial_asterisk_is_not_a_participant(): void
    {
        $path = $this->sample([[
            'name' => 'Campo',
            'rows' => [
                ['NO.', 'NOMBRE COMPLETO', 'Matricula UNRC', 'Grupo'],
                [1, 'APELLIDOS NOMBRE', 'UNRC-01', 1],
                [null, null, null, '*'],
            ],
        ]]);

        try {
            $summary = $this->reader()->inspect($path);
            self::assertSame(1, $summary['rows']);
            self::assertSame(1, $summary['sheets']);
        } finally {
            @unlink($path);
        }
    }

    public function test_fifth_generation_requires_explicit_mode(): void
    {
        $path = $this->sample([[
            'name' => 'GRUPO 1',
            'rows' => [
                [null, 'GRUPO 1 GENERACION 5TA'],
                [null, null],
                [1, 'APELLIDOS NOMBRE'],
            ],
        ]]);

        try {
            $this->expectException(InvalidArgumentException::class);
            iterator_to_array($this->reader()->read($path), false);
        } finally {
            @unlink($path);
        }
    }

    public function test_fifth_generation_excludes_auxiliary_sheet(): void
    {
        $path = $this->sample([
            [
                'name' => 'GRUPO 1',
                'rows' => [
                    [null, 'GRUPO 1 GENERACION 5TA'],
                    [null, null],
                    [1, 'APELLIDOS NOMBRE'],
                    [2, 'APELLIDOS DOS'],
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
            $summary = $this->reader()->inspect(
                $path,
                null,
                [],
                '5A_GROUP_LIST'
            );

            self::assertSame(2, $summary['rows']);
            self::assertSame(1, $summary['sheets']);
            self::assertSame(2, $summary['profiles']['5A']);
        } finally {
            @unlink($path);
        }
    }
}
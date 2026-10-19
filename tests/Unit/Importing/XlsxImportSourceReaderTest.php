<?php

namespace Tests\Unit\Importing;

use App\Importing\ImportSourceRow;
use App\Importing\InvalidXlsxImportSource;
use App\Importing\Readers\XlsxImportSourceReader;
use DateTimeImmutable;
use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\TestCase;

class XlsxImportSourceReaderTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                self::assertTrue(
                    @unlink($file),
                    'El lector debe liberar el archivo XLSX despues de leerlo.'
                );
            }
        }

        parent::tearDown();
    }

    public function test_reads_headers_and_preserves_physical_row_numbers(): void
    {
        $file = $this->createWorkbook([
            [
                ' nombres ',
                'apellido_paterno',
            ],
            [
                'MARIA',
                'LOPEZ',
            ],
            [
                null,
                null,
            ],
            [
                'JUAN',
                'PEREZ',
            ],
        ]);

        $rows = iterator_to_array(
            (new XlsxImportSourceReader)->read(
                $file
            ),
            false
        );

        self::assertCount(
            2,
            $rows
        );

        self::assertContainsOnlyInstancesOf(
            ImportSourceRow::class,
            $rows
        );

        self::assertSame(
            [
                2,
                4,
            ],
            array_map(
                static fn (ImportSourceRow $row): int => $row->rowNumber,
                $rows
            )
        );

        self::assertEquals(
            [
                'nombres' => 'MARIA',
                'apellido_paterno' => 'LOPEZ',
            ],
            $rows[0]->rawData
        );

        self::assertEquals(
            [
                'nombres' => 'JUAN',
                'apellido_paterno' => 'PEREZ',
            ],
            $rows[1]->rawData
        );
    }

    public function test_reads_only_first_sheet(): void
    {
        $file = $this->createWorkbook(
            [
                [
                    'nombres',
                ],
                [
                    'PRIMERA HOJA',
                ],
            ],
            [
                [
                    'nombres',
                ],
                [
                    'SEGUNDA HOJA',
                ],
            ]
        );

        $rows = iterator_to_array(
            (new XlsxImportSourceReader)->read(
                $file
            ),
            false
        );

        self::assertCount(
            1,
            $rows
        );

        self::assertEquals(
            [
                'nombres' => 'PRIMERA HOJA',
            ],
            $rows[0]->rawData
        );
    }

    public function test_preserves_scalars_and_normalizes_dates(): void
    {
        $date = new DateTimeImmutable(
            '2026-10-19 10:30:00'
        );

        $file = $this->createWorkbook([
            [
                'texto',
                'entero',
                'decimal',
                'booleano',
                'fecha',
            ],
            [
                'VALOR',
                25,
                10.5,
                true,
                $date,
            ],
        ]);

        $rows = iterator_to_array(
            (new XlsxImportSourceReader)->read(
                $file
            ),
            false
        );

        self::assertCount(
            1,
            $rows
        );

        self::assertSame(
            2,
            $rows[0]->rowNumber
        );

        self::assertEquals(
            [
                'texto' => 'VALOR',
                'entero' => 25,
                'decimal' => 10.5,
                'booleano' => true,
                'fecha' => '2026-10-19T10:30:00',
            ],
            $rows[0]->rawData
        );
    }

    public function test_rejects_duplicate_headers_after_trimming(): void
    {
        $file = $this->createWorkbook([
            [
                'nombres',
                ' nombres ',
            ],
            [
                'MARIA',
                'LOPEZ',
            ],
        ]);

        $this->expectException(
            InvalidXlsxImportSource::class
        );

        iterator_to_array(
            (new XlsxImportSourceReader)->read(
                $file
            )
        );
    }

    public function test_rejects_empty_header_inside_template(): void
    {
        $file = $this->createWorkbook([
            [
                'nombres',
                '',
                'apellido_paterno',
            ],
            [
                'MARIA',
                'IGNORADO',
                'LOPEZ',
            ],
        ]);

        $this->expectException(
            InvalidXlsxImportSource::class
        );

        iterator_to_array(
            (new XlsxImportSourceReader)->read(
                $file
            )
        );
    }

    public function test_rejects_nonexistent_file(): void
    {
        $missing = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'siga-xlsx-inexistente-'
            .bin2hex(random_bytes(8))
            .'.xlsx';

        $this->expectException(
            InvalidXlsxImportSource::class
        );

        iterator_to_array(
            (new XlsxImportSourceReader)->read(
                $missing
            )
        );
    }

    /**
     * @param  list<mixed>  $values
     */
    private function createRow(
        array $values
    ): Row {
        $columnStyles = [];

        foreach ($values as $index => $value) {
            if ($value instanceof DateTimeInterface) {
                $columnStyles[$index] = (new Style)
                    ->setFormat(
                        'yyyy-mm-dd hh:mm:ss'
                    );
            }
        }

        return Row::fromValuesWithStyles(
            $values,
            null,
            $columnStyles
        );
    }

    /**
     * @param  list<list<mixed>>  $firstSheet
     * @param  null|list<list<mixed>>  $secondSheet
     */
    private function createWorkbook(
        array $firstSheet,
        ?array $secondSheet = null
    ): string {
        $file = tempnam(
            sys_get_temp_dir(),
            'siga-xlsx-'
        );

        if ($file === false) {
            self::fail(
                'No fue posible crear el archivo temporal.'
            );
        }

        unlink($file);

        $file .= '.xlsx';

        $writer = new Writer;
        $writer->openToFile($file);

        foreach ($firstSheet as $values) {
            $writer->addRow(
                $this->createRow($values)
            );
        }

        if ($secondSheet !== null) {
            $writer->addNewSheetAndMakeItCurrent();

            foreach ($secondSheet as $values) {
                $writer->addRow(
                    $this->createRow($values)
                );
            }
        }

        $writer->close();

        $this->temporaryFiles[] = $file;

        return $file;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use App\Actions\Academic\ValidateAcademicRosterXlsx;
use App\Importing\Readers\XlsxImportSourceReader;
use DomainException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\TestCase;

final class ValidateAcademicRosterXlsxTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    private function headers(): array
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

    private function sample(
        int $number = 1,
        string $matricula = 'FIEAIIG826C001',
        string $rfc = 'AEGD000215RL3',
        int $group = 1,
        int $position = 1
    ): array {
        return [
            $number,
            $matricula,
            $rfc,
            'ACEVEDO GUZMAN DIEGO JOSE',
            '8va ',
            $group,
            $position,
        ];
    }

    private function workbook(array $matrix): string
    {
        $base = tempnam(
            sys_get_temp_dir(),
            'siga-f1-r1-'
        );

        self::assertNotFalse($base);

        unlink($base);
        $path = $base.'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);

        try {
            foreach ($matrix as $values) {
                $writer->addRow(
                    Row::fromValues($values)
                );
            }
        } finally {
            $writer->close();
        }

        $this->files[] = $path;

        return $path;
    }

    private function inspect(array $matrix): array
    {
        $validator = new ValidateAcademicRosterXlsx(
            new XlsxImportSourceReader
        );

        return $validator->inspect(
            $this->workbook($matrix)
        );
    }

    public function test_generation_and_matricula(): void
    {
        $result = $this->inspect([
            $this->headers(),
            $this->sample(),
        ]);

        self::assertSame(
            '8VA',
            $result['generacion_detectada']
        );

        self::assertSame(
            1,
            $result['summary']['total']
        );

        self::assertSame(
            1,
            $result['summary']['validos']
        );

        self::assertSame(
            'FIEAIIG826C001',
            $result['filas'][0]['matricula']
        );

        self::assertFalse($result['persistido']);
        self::assertSame(0, $result['usuarios_creados']);
        self::assertSame(0, $result['matriculas_generadas']);
    }

    public function test_invalid_rfc_is_flagged(): void
    {
        $result = $this->inspect([
            $this->headers(),
            $this->sample(
                1,
                'FIEAIIG826C001',
                'AEGD000215RL'
            ),
        ]);

        self::assertSame(
            1,
            $result['summary']['con_errores']
        );

        self::assertContains(
            'RFC requiere revision de formato',
            $result['filas'][0]['errores']
        );
    }

    public function test_matricula_suffix_is_not_changed(): void
    {
        $result = $this->inspect([
            $this->headers(),
            $this->sample(
                2,
                'FIEAIIG826C999',
                'AOSA990907IM6'
            ),
        ]);

        self::assertSame(
            1,
            $result['summary']['con_observaciones']
        );

        self::assertSame(
            'FIEAIIG826C999',
            $result['filas'][0]['matricula']
        );
    }

    public function test_duplicate_matricula(): void
    {
        $result = $this->inspect([
            $this->headers(),
            $this->sample(),
            $this->sample(
                2,
                'FIEAIIG826C001',
                'AOSA990907IM6',
                1,
                2
            ),
        ]);

        self::assertSame(
            2,
            $result['summary']['total']
        );

        self::assertSame(
            1,
            $result['summary']['con_errores']
        );

        self::assertContains(
            'Matricula duplicada',
            $result['filas'][1]['errores']
        );
    }

    public function test_wrong_headers_raise_domain_exception(): void
    {
        $this->expectException(
            DomainException::class
        );

        $this->inspect([
            ['RFC', 'NOMBRE'],
            ['AEGD000215RL3', 'DIEGO'],
        ]);
    }

    public function test_empty_workbook_data_is_rejected(): void
    {
        $this->expectException(
            DomainException::class
        );

        $this->inspect([
            $this->headers(),
        ]);
    }
}

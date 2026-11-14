<?php

namespace App\Importing\Readers;

use App\Importing\ImportSourceRow;
use App\Importing\InvalidXlsxImportSource;
use DateInterval;
use DateTimeInterface;
use Generator;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

class XlsxImportSourceReader
{
    /**
     * @return Generator<int, ImportSourceRow>
     */
    public function read(
        string $filePath,
        ?array $selectedHeaders = null
    ): Generator {
        if (
            ! is_file($filePath)
            || ! is_readable($filePath)
        ) {
            throw new InvalidXlsxImportSource(
                'El archivo XLSX no existe o no puede leerse.'
            );
        }

        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $options->SHOULD_FORMAT_DATES = false;

        $reader = new Reader(
            $options
        );

        try {
            $reader->open(
                $filePath
            );

            $sheetIterator =
                $reader->getSheetIterator();

            foreach ($sheetIterator as $sheet) {
                $headers = null;

                $rowIterator =
                    $sheet->getRowIterator();

                foreach (
                    $rowIterator as $rowNumber => $row
                ) {
                    $values = $row->toArray();

                    if ($headers === null) {
                        $headers =
                            $this->normalizeHeaders(
                                $values,
                                $selectedHeaders
                            );

                        continue;
                    }

                    if (
                        $this->isEmptyRow(
                            $values
                        )
                    ) {
                        continue;
                    }

                    if ($selectedHeaders === null) {
                        $headerWidth = count($headers);
                        $extraValues = array_slice($values, $headerWidth);

                        if (! $this->isEmptyRow($extraValues)) {
                            throw new InvalidXlsxImportSource(
                                'Los datos XLSX exceden las columnas declaradas.'
                            );
                        }
                    }

                    $rawData = [];

                    foreach (
                        $headers as $index => $header
                    ) {
                        $rawData[$header] =
                            $this->normalizeValue(
                                $values[$index]
                                    ?? null
                            );
                    }

                    if (
                        $selectedHeaders !== null &&
                        $this->isEmptyRow(array_values($rawData))
                    ) {
                        continue;
                    }

                    yield new ImportSourceRow(
                        (int) $rowNumber,
                        $rawData
                    );
                }

                if ($headers === null) {
                    throw new InvalidXlsxImportSource(
                        'La primera hoja no contiene encabezados.'
                    );
                }

                return;
            }

            throw new InvalidXlsxImportSource(
                'El archivo XLSX no contiene hojas.'
            );
        } catch (
            InvalidXlsxImportSource $error
        ) {
            throw $error;
        } catch (
            OpenSpoutException $error
        ) {
            throw new InvalidXlsxImportSource(
                'No fue posible leer el archivo XLSX.',
                $error
            );
        } finally {
            $reader->close();

            unset(
                $row,
                $rowIterator,
                $sheet,
                $sheetIterator,
                $reader
            );

            gc_collect_cycles();
        }
    }

    /**
     * @param  list<mixed>  $values
     * @return list<string>
     */
    private function normalizeHeaders(
        array $values,
        ?array $selectedHeaders = null
    ): array {
        if ($selectedHeaders !== null) {
            if ($selectedHeaders === []) {
                throw new InvalidXlsxImportSource(
                    'No se indicaron columnas institucionales.'
                );
            }

            $available = [];

            foreach ($values as $index => $value) {
                if (! is_string($value) || trim($value) === '') {
                    continue;
                }

                $key = mb_strtoupper(trim(
                    preg_replace('/\s+/u', ' ', $value) ?? $value
                ));

                $available[$key][] = $index;
            }

            $resolved = [];

            foreach ($selectedHeaders as $header) {
                if (! is_string($header) || trim($header) === '') {
                    throw new InvalidXlsxImportSource(
                        'Columna institucional invalida.'
                    );
                }

                $key = mb_strtoupper(trim(
                    preg_replace('/\s+/u', ' ', $header) ?? $header
                ));

                $positions = $available[$key] ?? [];

                if (count($positions) !== 1) {
                    throw new InvalidXlsxImportSource(
                        "Columna requerida ausente o duplicada: {$header}."
                    );
                }

                $resolved[$positions[0]] = $header;
            }

            return $resolved;
        }

        while (
            $values !== []
            && $this->isBlankValue(
                $values[array_key_last($values)]
            )
        ) {
            array_pop($values);
        }

        if ($values === []) {
            throw new InvalidXlsxImportSource(
                'La fila de encabezados esta vacia.'
            );
        }

        $headers = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                throw new InvalidXlsxImportSource(
                    'Los encabezados XLSX deben ser texto.'
                );
            }

            $header = trim(
                $value
            );

            if ($header === '') {
                throw new InvalidXlsxImportSource(
                    'Los encabezados XLSX no pueden estar vacios.'
                );
            }

            if (
                in_array(
                    $header,
                    $headers,
                    true
                )
            ) {
                throw new InvalidXlsxImportSource(
                    "Encabezado XLSX duplicado: {$header}."
                );
            }

            $headers[] = $header;
        }

        return $headers;
    }

    /**
     * @param  list<mixed>  $values
     */
    private function isEmptyRow(
        array $values
    ): bool {
        foreach ($values as $value) {
            if (! $this->isBlankValue($value)) {
                return false;
            }
        }

        return true;
    }

    private function isBlankValue(
        mixed $value
    ): bool {
        return $value === null
            || (
                is_string($value)
                && trim($value) === ''
            );
    }

    private function normalizeValue(
        mixed $value
    ): bool|float|int|string|null {
        if ($value instanceof DateTimeInterface) {
            return $value->format(
                'Y-m-d\TH:i:s'
            );
        }

        if ($value instanceof DateInterval) {
            return $value->format(
                '%rPT%hH%iM%sS'
            );
        }

        if (
            $value === null
            || is_bool($value)
            || is_int($value)
            || is_float($value)
            || is_string($value)
        ) {
            return $value;
        }

        throw new InvalidXlsxImportSource(
            'El XLSX contiene un tipo de celda no soportado.'
        );
    }
    // SIGA_ACADEMIA_SHEET_V3
    public function readAcademicSheetMetadata(string $filePath): ?array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new InvalidXlsxImportSource(
                'No se puede leer el archivo Excel.'
            );
        }

        $normalize = static function (mixed $value): string {
            if (!is_scalar($value)) {
                return '';
            }

            $text = mb_strtoupper(trim((string) $value), 'UTF-8');

            $text = strtr($text, [
                'Á' => 'A', 'É' => 'E', 'Í' => 'I',
                'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U',
            ]);

            return preg_replace('/\s+/u', ' ', $text) ?? $text;
        };

        $keys = [
            'CODIGO DE PROGRAMA' => 'codigo_programa',
            'NOMBRE DE PROGRAMA' => 'nombre_programa',
            'TIPO DE COHORTE' => 'tipo_cohorte',
            'NUMERO DE GENERACION' => 'numero_generacion',
            'ANO OFICIAL DE GENERACION' => 'anio_generacion',
            'AÑO OFICIAL DE GENERACION' => 'anio_generacion',
            'SEDE INICIAL (OPCIONAL)' => 'sede_inicial',
        ];

        $options = new Options;
        $reader = new Reader($options);
        $opened = false;

        try {
            $reader->open($filePath);
            $opened = true;

            foreach ($reader->getSheetIterator() as $sheet) {
                if ($normalize($sheet->getName()) !== 'ACADEMIA') {
                    continue;
                }

                $metadata = [];
                $groups = [];
                $readingGroups = false;
                $rowCount = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    if (++$rowCount > 1000) {
                        throw new InvalidXlsxImportSource(
                            'La hoja ACADEMIA supera 1000 filas.'
                        );
                    }

                    // Solo interesan A y B; las notas y columnas
                    // adicionales no afectan la importacion.
                    $cells = $row->toArray();
                    $label = $normalize($cells[0] ?? null);
                    $value = trim((string) ($cells[1] ?? ''));

                    if ($label === 'CODIGO GRUPO') {
                        $readingGroups = true;
                        continue;
                    }

                    if ($readingGroups) {
                        if (str_starts_with($label, 'COMO UTILIZAR')) {
                            break;
                        }

                        if ($label === '') {
                            if ($groups !== []) {
                                break;
                            }
                            continue;
                        }

                        $groups[] = [
                            'codigo' => $label,
                            'especializacion' => $value,
                        ];

                        continue;
                    }

                    if (array_key_exists($label, $keys)) {
                        $metadata[$keys[$label]] = $value;
                    }
                }

                $metadata['grupos'] = $groups;

                return $metadata;
            }

            return null;
        } finally {
            if ($opened) {
                $reader->close();
            }
        }
    }

}

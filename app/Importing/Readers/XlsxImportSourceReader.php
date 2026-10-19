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
        string $filePath
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
                                $values
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
        array $values
    ): array {
        if ($values === []) {
            throw new InvalidXlsxImportSource(
                'La fila de encabezados esta vacia.'
            );
        }

        $headers = [];

        foreach ($values as $value) {
            if (
                ! is_string($value)
                && ! is_int($value)
                && ! is_float($value)
            ) {
                throw new InvalidXlsxImportSource(
                    'Los encabezados XLSX deben ser texto o numeros.'
                );
            }

            $header = trim(
                (string) $value
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
            if ($value === null) {
                continue;
            }

            if (
                is_string($value)
                && trim($value) === ''
            ) {
                continue;
            }

            return false;
        }

        return true;
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
}

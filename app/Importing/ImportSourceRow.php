<?php

namespace App\Importing;

use InvalidArgumentException;

readonly class ImportSourceRow
{
    /**
     * @param  array<string, mixed>  $rawData
     */
    public function __construct(
        public int $rowNumber,
        public array $rawData
    ) {
        if ($rowNumber <= 0) {
            throw new InvalidArgumentException(
                'El numero de fila fuente debe ser mayor que cero.'
            );
        }
    }
}

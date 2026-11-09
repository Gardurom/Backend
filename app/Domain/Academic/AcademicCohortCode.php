<?php

namespace App\Domain\Academic;

use InvalidArgumentException;

final class AcademicCohortCode
{
    public static function format(
        string $program,
        string $type,
        int $number,
        int $year
    ): string {
        if (!preg_match('/^[A-Z0-9]+$/D', $program)) {
            throw new InvalidArgumentException(
                'Codigo de programa invalido.'
            );
        }

        if (!in_array($type, ['G', 'E'], true)) {
            throw new InvalidArgumentException(
                'Tipo de cohorte invalido.'
            );
        }

        if ($number < 1) {
            throw new InvalidArgumentException(
                'Numero de cohorte invalido.'
            );
        }

        if ($year < 1900 || $year > 9999) {
            throw new InvalidArgumentException(
                'Anio academico invalido.'
            );
        }

        return implode('-', [
            $program,
            $type . str_pad(
                (string) $number,
                2,
                '0',
                STR_PAD_LEFT
            ),
            (string) $year,
        ]);
    }
}
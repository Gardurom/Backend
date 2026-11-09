<?php

namespace App\Domain\Academic;

use InvalidArgumentException;

final class AcademicEnrollmentCode
{
    public static function format(
        string $program,
        string $cohort,
        int $year,
        string $type,
        int $sequence
    ): string {
        foreach ([$program, $cohort] as $value) {
            if (!preg_match('/^[A-Z0-9]+$/D', $value)) {
                throw new InvalidArgumentException(
                    'Codigo institucional invalido.'
                );
            }
        }

        if ($year < 1900 || $year > 9999) {
            throw new InvalidArgumentException(
                'Anio academico invalido.'
            );
        }

        if (!in_array($type, ['C', 'I'], true)) {
            throw new InvalidArgumentException(
                'Tipo de participante invalido.'
            );
        }

        if ($sequence < 1) {
            throw new InvalidArgumentException(
                'Consecutivo invalido.'
            );
        }

        return implode('-', [
            $program,
            $cohort,
            (string) $year,
            $type,
            str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
        ]);
    }
}
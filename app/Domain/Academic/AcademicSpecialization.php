<?php

namespace App\Domain\Academic;

use InvalidArgumentException;

final class AcademicSpecialization
{
    public const CAMPO = 'CAMPO';
    public const GABINETE = 'GABINETE';

    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = strtoupper(trim($value));

        if (! in_array($normalized, [
            self::CAMPO,
            self::GABINETE,
        ], true)) {
            throw new InvalidArgumentException(
                'SIGA: especializacion academica no valida.'
            );
        }

        return $normalized;
    }

    public static function isValid(?string $value): bool
    {
        try {
            self::normalize($value);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
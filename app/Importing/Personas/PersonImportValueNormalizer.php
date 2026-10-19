<?php

namespace App\Importing\Personas;

final class PersonImportValueNormalizer
{
    public static function text(
        mixed $value
    ): mixed {
        if (! is_string($value)) {
            return $value;
        }

        $normalized = trim(
            $value
        );

        return $normalized === ''
            ? null
            : $normalized;
    }

    public static function uppercase(
        mixed $value
    ): mixed {
        $normalized = self::text(
            $value
        );

        if (! is_string($normalized)) {
            return $normalized;
        }

        return strtoupper(
            $normalized
        );
    }

    public static function lowercase(
        mixed $value
    ): mixed {
        $normalized = self::text(
            $value
        );

        if (! is_string($normalized)) {
            return $normalized;
        }

        return strtolower(
            $normalized
        );
    }
}

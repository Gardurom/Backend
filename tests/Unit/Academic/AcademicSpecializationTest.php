<?php

namespace Tests\Unit\Academic;

use App\Domain\Academic\AcademicSpecialization;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AcademicSpecializationTest extends TestCase
{
    public function test_accepts_campo(): void
    {
        self::assertSame(
            'CAMPO',
            AcademicSpecialization::normalize(' campo ')
        );
    }

    public function test_accepts_gabinete(): void
    {
        self::assertSame(
            'GABINETE',
            AcademicSpecialization::normalize('gabinete')
        );
    }

    public function test_allows_group_without_initial_specialization(): void
    {
        self::assertNull(
            AcademicSpecialization::normalize(null)
        );

        self::assertNull(
            AcademicSpecialization::normalize('')
        );
    }

    public function test_rejects_unknown_specialization(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AcademicSpecialization::normalize('OTRA');
    }

    public function test_rejects_invalid_value_without_throwing(): void
    {
        self::assertFalse(
            AcademicSpecialization::isValid('OTRA')
        );
    }

    public function test_accepts_valid_values(): void
    {
        self::assertTrue(
            AcademicSpecialization::isValid('CAMPO')
        );

        self::assertTrue(
            AcademicSpecialization::isValid('GABINETE')
        );

        self::assertTrue(
            AcademicSpecialization::isValid(null)
        );
    }
}
<?php

use App\Domain\Academic\AcademicCohortCode;
use PHPUnit\Framework\TestCase;

require_once __DIR__ .
    '/../../../app/Domain/Academic/AcademicCohortCode.php';

final class AcademicCohortCodeTest extends TestCase
{
    public function test_generation(): void
    {
        self::assertSame(
            'FOR-G12-2026',
            AcademicCohortCode::format('FOR', 'G', 12, 2026)
        );
    }

    public function test_command_cohort(): void
    {
        self::assertSame(
            'MAND-E03-2026',
            AcademicCohortCode::format('MAND', 'E', 3, 2026)
        );
    }

    public function test_large_generation_number(): void
    {
        self::assertSame(
            'FOR-G100-2026',
            AcademicCohortCode::format('FOR', 'G', 100, 2026)
        );
    }

    public function test_invalid_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AcademicCohortCode::format('FOR', 'X', 1, 2026);
    }

    public function test_invalid_number(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AcademicCohortCode::format('FOR', 'G', 0, 2026);
    }

    public function test_invalid_program(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AcademicCohortCode::format('FOR-', 'G', 1, 2026);
    }
}
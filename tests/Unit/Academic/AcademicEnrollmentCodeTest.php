<?php

use App\Domain\Academic\AcademicEnrollmentCode;
use PHPUnit\Framework\TestCase;

require_once __DIR__ .
    '/../../../app/Domain/Academic/AcademicEnrollmentCode.php';

final class AcademicEnrollmentCodeTest extends TestCase
{
    public function test_formats_cadet_code(): void
    {
        self::assertSame(
            'FOR-G12-2026-C-001',
            AcademicEnrollmentCode::format(
                'FOR', 'G12', 2026, 'C', 1
            )
        );
    }

    public function test_formats_command_training_code(): void
    {
        self::assertSame(
            'MAND-E03-2026-I-025',
            AcademicEnrollmentCode::format(
                'MAND', 'E03', 2026, 'I', 25
            )
        );
    }

    public function test_sequence_expands_after_999(): void
    {
        self::assertSame(
            'FOR-G12-2026-C-1000',
            AcademicEnrollmentCode::format(
                'FOR', 'G12', 2026, 'C', 1000
            )
        );
    }

    public function test_rejects_zero_sequence(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AcademicEnrollmentCode::format(
            'FOR', 'G12', 2026, 'C', 0
        );
    }

    public function test_rejects_unknown_participant_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AcademicEnrollmentCode::format(
            'FOR', 'G12', 2026, 'X', 1
        );
    }
}
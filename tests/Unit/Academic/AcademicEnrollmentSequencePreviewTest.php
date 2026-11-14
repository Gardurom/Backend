<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use App\Domain\Academic\AcademicEnrollmentSequencePreview;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;

require_once __DIR__
    . '/../../../app/Domain/Academic/'
    . 'AcademicEnrollmentSequencePreview.php';

final class AcademicEnrollmentSequencePreviewTest extends TestCase
{
    private const COHORT =
        '0193a457-61b0-78a1-8f12-abcdef123456';

    private const ENROLLMENT_A =
        '0193a457-61b0-78a1-8f12-123456789abc';

    private const ENROLLMENT_B =
        '0193a457-61b0-78a1-8f12-abcdef654321';

    private static function scope(): AcademicEnrollmentSequencePreview
    {
        return AcademicEnrollmentSequencePreview::forScope(
            self::COHORT,
            'C'
        );
    }

    public function test_normalizes_cohort_uuid(): void
    {
        $scope = AcademicEnrollmentSequencePreview::forScope(
            strtoupper(self::COHORT),
            'C'
        );

        self::assertSame(self::COHORT, $scope->cohortId);
    }

    public function test_separates_cadet_and_command_scopes(): void
    {
        $cadet = self::scope();

        $command = AcademicEnrollmentSequencePreview::forScope(
            self::COHORT,
            'I'
        );

        self::assertNotSame(
            $cadet->scopeKey(),
            $command->scopeKey()
        );
    }

    public function test_rejects_cohort_kind_as_participant_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AcademicEnrollmentSequencePreview::forScope(
            self::COHORT,
            'G'
        );
    }

    public function test_rejects_lowercase_participant_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AcademicEnrollmentSequencePreview::forScope(
            self::COHORT,
            'c'
        );
    }

    public function test_rejects_invalid_cohort_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AcademicEnrollmentSequencePreview::forScope(
            'no-es-uuid',
            'C'
        );
    }

    public function test_first_consecutive_starts_at_one(): void
    {
        $plan = self::scope()->previewAfter(
            0,
            [self::ENROLLMENT_A]
        );

        self::assertSame(1, $plan[0]['consecutivo']);
    }

    public function test_assigns_distinct_consecutives_in_order(): void
    {
        $plan = self::scope()->previewAfter(
            0,
            [self::ENROLLMENT_B, self::ENROLLMENT_A]
        );

        self::assertSame(self::ENROLLMENT_B, $plan[0]['id_incorporacion']);
        self::assertSame(1, $plan[0]['consecutivo']);
        self::assertSame(self::ENROLLMENT_A, $plan[1]['id_incorporacion']);
        self::assertSame(2, $plan[1]['consecutivo']);
    }

    public function test_does_not_restart_after_999(): void
    {
        $plan = self::scope()->previewAfter(
            999,
            [self::ENROLLMENT_A]
        );

        self::assertSame(1000, $plan[0]['consecutivo']);
    }

    public function test_is_deterministic_without_persistence(): void
    {
        $scope = self::scope();
        $ids = [self::ENROLLMENT_A, self::ENROLLMENT_B];

        self::assertSame(
            $scope->previewAfter(10, $ids),
            $scope->previewAfter(10, $ids)
        );
    }

    public function test_rejects_empty_batch(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::scope()->previewAfter(0, []);
    }

    public function test_rejects_negative_counter(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::scope()->previewAfter(-1, [self::ENROLLMENT_A]);
    }

    public function test_rejects_duplicate_enrollments(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::scope()->previewAfter(
            0,
            [
                self::ENROLLMENT_A,
                strtoupper(self::ENROLLMENT_A),
            ]
        );
    }

    public function test_rejects_invalid_enrollment_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::scope()->previewAfter(0, ['invalido']);
    }

    public function test_rejects_integer_overflow(): void
    {
        $this->expectException(OverflowException::class);

        self::scope()->previewAfter(
            PHP_INT_MAX,
            [self::ENROLLMENT_A]
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use App\Domain\Academic\AcademicConfirmationFingerprint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once __DIR__
    . '/../../../app/Domain/Academic/'
    . 'AcademicConfirmationFingerprint.php';

final class AcademicConfirmationFingerprintTest extends TestCase
{
    private const OP =
        '0193a457-61b0-78a1-8f12-111111111111';

    private const OP2 =
        '0193a457-61b0-78a1-8f12-222222222222';

    private const COHORT =
        '0193a457-61b0-78a1-8f12-333333333333';

    private const COHORT2 =
        '0193a457-61b0-78a1-8f12-444444444444';

    private const A =
        '0193a457-61b0-78a1-8f12-555555555555';

    private const B =
        '0193a457-61b0-78a1-8f12-666666666666';

    private static function make(
        string $operation = self::OP,
        string $cohort = self::COHORT,
        string $type = 'C',
        string $mode = 'INICIAL',
        array $ids = [self::A, self::B]
    ): AcademicConfirmationFingerprint {
        return AcademicConfirmationFingerprint::build(
            $operation, $cohort, $type, $mode, $ids
        );
    }

    public function test_builds_sha256_fingerprint(): void
    {
        self::assertMatchesRegularExpression(
            '/\A[0-9a-f]{64}\z/',
            self::make()->fingerprint
        );
    }

    public function test_is_deterministic(): void
    {
        self::assertSame(
            self::make()->fingerprint,
            self::make()->fingerprint
        );
    }

    public function test_normalizes_uuid_case(): void
    {
        $input = self::make(
            strtoupper(self::OP),
            strtoupper(self::COHORT),
            'C',
            'INICIAL',
            [strtoupper(self::A)]
        );

        self::assertSame(self::OP, $input->operationId);
        self::assertSame(self::COHORT, $input->cohortId);
        self::assertSame([self::A], $input->orderedEnrollmentIds);
    }

    public function test_operation_id_is_separate_from_payload_hash(): void
    {
        self::assertSame(
            self::make(self::OP)->fingerprint,
            self::make(self::OP2)->fingerprint
        );
    }

    public function test_order_changes_fingerprint(): void
    {
        self::assertNotSame(
            self::make()->fingerprint,
            self::make(
                self::OP,
                self::COHORT,
                'C',
                'INICIAL',
                [self::B, self::A]
            )->fingerprint
        );
    }

    public function test_cohort_changes_fingerprint(): void
    {
        self::assertNotSame(
            self::make()->fingerprint,
            self::make(self::OP, self::COHORT2)->fingerprint
        );
    }

    public function test_participant_type_changes_fingerprint(): void
    {
        self::assertNotSame(
            self::make()->fingerprint,
            self::make(self::OP, self::COHORT, 'I')->fingerprint
        );
    }

    public function test_mode_changes_fingerprint(): void
    {
        self::assertNotSame(
            self::make(
                self::OP, self::COHORT, 'C', 'INICIAL', [self::A]
            )->fingerprint,
            self::make(
                self::OP, self::COHORT, 'C', 'POSTERIOR', [self::A]
            )->fingerprint
        );
    }

    public function test_rejects_duplicate_enrollments(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::make(
            self::OP,
            self::COHORT,
            'C',
            'INICIAL',
            [self::A, strtoupper(self::A)]
        );
    }

    public function test_rejects_empty_batch(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::make(
            self::OP, self::COHORT, 'C', 'INICIAL', []
        );
    }

    public function test_rejects_multiple_posterior_enrollments(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::make(
            self::OP, self::COHORT, 'C', 'POSTERIOR',
            [self::A, self::B]
        );
    }

    public function test_rejects_invalid_participant_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::make(self::OP, self::COHORT, 'G');
    }

    public function test_rejects_invalid_mode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::make(self::OP, self::COHORT, 'C', 'OTRA');
    }

    public function test_rejects_invalid_operation_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::make('no-es-uuid');
    }

    public function test_rejects_invalid_cohort_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::make(self::OP, 'no-es-uuid');
    }

    public function test_rejects_invalid_enrollment_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::make(
            self::OP, self::COHORT, 'C', 'INICIAL',
            ['invalido']
        );
    }

    public function test_rejects_nonsequential_array(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::make(
            self::OP, self::COHORT, 'C', 'INICIAL',
            [2 => self::A]
        );
    }
}

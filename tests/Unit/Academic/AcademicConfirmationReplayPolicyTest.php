<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use App\Domain\Academic\AcademicConfirmationFingerprint;
use App\Domain\Academic\AcademicConfirmationReplayPolicy;
use DomainException;
use LogicException;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

require_once __DIR__
    . '/../../../app/Domain/Academic/'
    . 'AcademicConfirmationFingerprint.php';

require_once __DIR__
    . '/../../../app/Domain/Academic/'
    . 'AcademicConfirmationReplayPolicy.php';

final class AcademicConfirmationReplayPolicyTest extends TestCase
{
    private const OP =
        '0193a457-61b0-78a1-8f12-111111111111';

    private const OTHER_OP =
        '0193a457-61b0-78a1-8f12-222222222222';

    private const COHORT =
        '0193a457-61b0-78a1-8f12-333333333333';

    private const OTHER_COHORT =
        '0193a457-61b0-78a1-8f12-444444444444';

    private const ENROLLMENT =
        '0193a457-61b0-78a1-8f12-555555555555';

    private static function candidate():
        AcademicConfirmationFingerprint
    {
        return AcademicConfirmationFingerprint::build(
            self::OP,
            self::COHORT,
            'C',
            'POSTERIOR',
            [self::ENROLLMENT]
        );
    }

    private static function recorded(): array
    {
        $candidate = self::candidate();

        return [
            'id_operacion' => $candidate->operationId,
            'id_cohorte' => $candidate->cohortId,
            'tipo_participante' => $candidate->participantType,
            'modalidad' => $candidate->mode,
            'huella_solicitud' => $candidate->fingerprint,
            'resultado' => ['incorporaciones' => 1],
            'confirmada_at' => '2026-11-11T10:00:00+00:00',
        ];
    }

    public function test_identifies_new_operation(): void
    {
        self::assertSame(
            AcademicConfirmationReplayPolicy::NEW_OPERATION,
            AcademicConfirmationReplayPolicy::decide(
                self::candidate(),
                null
            )
        );
    }

    public function test_accepts_identical_replay(): void
    {
        self::assertSame(
            AcademicConfirmationReplayPolicy::REPLAY,
            AcademicConfirmationReplayPolicy::decide(
                self::candidate(),
                self::recorded()
            )
        );
    }

    public function test_rejects_changed_hash(): void
    {
        $recorded = self::recorded();
        $recorded['huella_solicitud'] = str_repeat('a', 64);

        $this->expectException(DomainException::class);

        AcademicConfirmationReplayPolicy::decide(
            self::candidate(),
            $recorded
        );
    }

    public function test_rejects_changed_cohort(): void
    {
        $recorded = self::recorded();
        $recorded['id_cohorte'] = self::OTHER_COHORT;

        $this->expectException(DomainException::class);

        AcademicConfirmationReplayPolicy::decide(
            self::candidate(),
            $recorded
        );
    }

    public function test_rejects_changed_participant_type(): void
    {
        $recorded = self::recorded();
        $recorded['tipo_participante'] = 'I';

        $this->expectException(DomainException::class);

        AcademicConfirmationReplayPolicy::decide(
            self::candidate(),
            $recorded
        );
    }

    public function test_rejects_changed_mode(): void
    {
        $recorded = self::recorded();
        $recorded['modalidad'] = 'INICIAL';

        $this->expectException(DomainException::class);

        AcademicConfirmationReplayPolicy::decide(
            self::candidate(),
            $recorded
        );
    }

    public function test_rejects_lookup_for_another_operation(): void
    {
        $recorded = self::recorded();
        $recorded['id_operacion'] = self::OTHER_OP;

        $this->expectException(LogicException::class);

        AcademicConfirmationReplayPolicy::decide(
            self::candidate(),
            $recorded
        );
    }

    public function test_rejects_missing_hash(): void
    {
        $recorded = self::recorded();
        unset($recorded['huella_solicitud']);

        $this->expectException(UnexpectedValueException::class);

        AcademicConfirmationReplayPolicy::decide(
            self::candidate(),
            $recorded
        );
    }

    public function test_rejects_invalid_hash_format(): void
    {
        $recorded = self::recorded();
        $recorded['huella_solicitud'] = 'no-es-sha256';

        $this->expectException(UnexpectedValueException::class);

        AcademicConfirmationReplayPolicy::decide(
            self::candidate(),
            $recorded
        );
    }

    public function test_rejects_missing_result(): void
    {
        $recorded = self::recorded();
        $recorded['resultado'] = null;

        $this->expectException(UnexpectedValueException::class);

        AcademicConfirmationReplayPolicy::decide(
            self::candidate(),
            $recorded
        );
    }

    public function test_rejects_missing_confirmation_date(): void
    {
        $recorded = self::recorded();
        $recorded['confirmada_at'] = null;

        $this->expectException(UnexpectedValueException::class);

        AcademicConfirmationReplayPolicy::decide(
            self::candidate(),
            $recorded
        );
    }

    public function test_does_not_return_recorded_result(): void
    {
        $recorded = self::recorded();
        $recorded['resultado'] = [
            'dato_institucional' => 'NO_EXPONER',
        ];

        $decision = AcademicConfirmationReplayPolicy::decide(
            self::candidate(),
            $recorded
        );

        self::assertSame(
            AcademicConfirmationReplayPolicy::REPLAY,
            $decision
        );

        self::assertIsString($decision);
        self::assertStringNotContainsString(
            'NO_EXPONER',
            $decision
        );
    }
}

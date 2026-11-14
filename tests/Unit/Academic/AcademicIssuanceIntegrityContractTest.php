<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use PHPUnit\Framework\TestCase;

final class AcademicIssuanceIntegrityContractTest extends TestCase
{
    private static function proposal(): string
    {
        $path = dirname(__DIR__, 3)
            . '/database/proposals/'
            . 'F3_P02_C9_academic_issuance_storage.php';

        $content = file_get_contents($path);

        self::assertNotFalse($content);

        return $content;
    }

    public function test_counter_trigger_is_declared(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            'CREATE FUNCTION institutional.protect_academic_sequence_counter()',
            $code
        );

        self::assertStringContainsString(
            'CREATE TRIGGER academic_sequence_counter_guard',
            $code
        );

        self::assertStringContainsString(
            'BEFORE UPDATE OR DELETE',
            $code
        );
    }

    public function test_counter_cannot_move_backwards(): void
    {
        self::assertStringContainsString(
            'NEW.ultimo_consecutivo < OLD.ultimo_consecutivo',
            self::proposal()
        );
    }

    public function test_counter_scope_is_immutable(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            'NEW.id_cohorte IS DISTINCT FROM OLD.id_cohorte',
            $code
        );

        self::assertStringContainsString(
            'NEW.tipo_participante IS DISTINCT FROM OLD.tipo_participante',
            $code
        );
    }

    public function test_initial_confirmation_cannot_revert(): void
    {
        self::assertStringContainsString(
            'OLD.inicial_confirmada AND NOT NEW.inicial_confirmada',
            self::proposal()
        );
    }

    public function test_counter_cannot_be_deleted(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            "IF TG_OP = 'DELETE' THEN",
            $code
        );

        self::assertStringContainsString(
            'eliminacion de contador no autorizada',
            $code
        );
    }

    public function test_reciprocal_matching_trigger_exists(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            'CREATE FUNCTION institutional.verify_academic_enrollment_issuance_match()',
            $code
        );

        self::assertStringContainsString(
            'CREATE CONSTRAINT TRIGGER academic_enrollment_has_matching_issuance',
            $code
        );

        self::assertStringContainsString(
            'CREATE CONSTRAINT TRIGGER academic_issuance_has_matching_enrollment',
            $code
        );
    }

    public function test_checks_both_sides_at_commit(): void
    {
        $code = self::proposal();

        self::assertSame(
            2,
            substr_count($code, 'DEFERRABLE INITIALLY DEFERRED')
        );

        self::assertStringContainsString(
            "TG_TABLE_NAME = 'academic_enrollments'",
            $code
        );

        self::assertStringContainsString(
            "TG_TABLE_NAME = 'academic_enrollment_issuances'",
            $code
        );
    }

    public function test_requires_exact_identity_and_dates(): void
    {
        $code = self::proposal();

        foreach ([
            'issuance.id_incorporacion = NEW.id_incorporacion',
            'issuance.id_cohorte = NEW.id_cohorte',
            'issuance.tipo_participante = NEW.tipo_participante',
            'issuance.matricula = NEW.matricula',
            'issuance.emitida_at = NEW.confirmada_at',
            'enrollment.id_incorporacion = NEW.id_incorporacion',
            'enrollment.id_cohorte = NEW.id_cohorte',
            'enrollment.tipo_participante = NEW.tipo_participante',
            'enrollment.matricula = NEW.matricula',
            'enrollment.confirmada_at = NEW.emitida_at',
        ] as $condition) {
            self::assertStringContainsString($condition, $code);
        }
    }

    public function test_restricts_new_function_privileges(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            'REVOKE ALL ON FUNCTION',
            $code
        );

        self::assertStringContainsString(
            'FROM PUBLIC',
            $code
        );

        self::assertStringContainsString(
            'FROM siga_app',
            $code
        );

        self::assertStringNotContainsString(
            'GRANT INSERT',
            $code
        );
    }

    public function test_keeps_proposal_outside_executable_migrations(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            "'siga_migrator'",
            $code
        );

        self::assertStringContainsString(
            'DB::transactionLevel() < 1',
            $code
        );

        self::assertStringContainsString(
            'SET ROLE siga_owner',
            $code
        );

        self::assertStringNotContainsString(
            'Schema::drop',
            $code
        );
    }
}

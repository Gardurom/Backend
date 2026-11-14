<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use PHPUnit\Framework\TestCase;

final class AcademicEnrollmentScopeContractTest extends TestCase
{
    private static function proposal(): string
    {
        $path = dirname(__DIR__, 3)
            . '/database/proposals/'
            . 'F3_P02_C1_academic_enrollments.php';

        $content = file_get_contents($path);

        self::assertNotFalse($content);

        return $content;
    }

    public function test_declares_participant_type(): void
    {
        self::assertStringContainsString(
            "string('tipo_participante', 1)",
            self::proposal()
        );
    }

    public function test_restricts_participant_type_to_c_or_i(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            'academic_enrollments_participant_type_check',
            $code
        );

        self::assertStringContainsString(
            "tipo_participante IN ('C', 'I')",
            $code
        );
    }

    public function test_declares_composite_issuance_reference(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            "['id_incorporacion', 'id_cohorte', 'tipo_participante']",
            $code
        );

        self::assertStringContainsString(
            'academic_enrollments_issue_scope_unique',
            $code
        );
    }

    public function test_freezes_confirmed_participant(): void
    {
        self::assertStringContainsString(
            'NEW.id_participante IS DISTINCT FROM OLD.id_participante',
            self::proposal()
        );
    }

    public function test_freezes_confirmed_cohort_and_type(): void
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

    public function test_preserves_confirmation_protection(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            'academic_enrollments_confirmation_immutable',
            $code
        );

        self::assertStringContainsString(
            'NEW.matricula IS DISTINCT FROM OLD.matricula',
            $code
        );

        self::assertStringContainsString(
            'NEW.confirmada_at IS DISTINCT FROM OLD.confirmada_at',
            $code
        );
    }

    public function test_keeps_migration_restricted(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            "'siga_migrator'",
            $code
        );

        self::assertStringContainsString(
            'GRANT SELECT ON TABLE',
            $code
        );

        self::assertStringNotContainsString(
            'GRANT INSERT ON TABLE',
            $code
        );

        self::assertStringNotContainsString(
            'Schema::drop',
            $code
        );
    }
}

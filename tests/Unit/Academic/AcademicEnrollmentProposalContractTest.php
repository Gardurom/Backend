<?php

namespace Tests\Unit\Academic;

use PHPUnit\Framework\TestCase;

final class AcademicEnrollmentProposalContractTest extends TestCase
{
    private static function migration(): string
    {
        $path = dirname(__DIR__, 3)
            . '/database/proposals/'
            . 'F3_P02_C1_academic_enrollments.php';

        $code = file_get_contents($path);

        self::assertNotFalse($code);

        return $code;
    }

    public function test_defines_independent_status_catalogs(): void
    {
        $code = self::migration();

        self::assertStringContainsString(
            'academic_enrollment_document_states',
            $code
        );

        self::assertStringContainsString(
            'academic_enrollment_academic_states',
            $code
        );

        self::assertStringContainsString(
            "string('estado_documental', 40)",
            $code
        );

        self::assertStringContainsString(
            "string('situacion_academica', 40)",
            $code
        );
    }

    public function test_reuses_participant_and_cohort(): void
    {
        $code = self::migration();

        self::assertStringContainsString(
            "references('id_participante')",
            $code
        );

        self::assertStringContainsString(
            "references('id_cohorte')",
            $code
        );

        self::assertStringContainsString(
            'institutional.academic_participants',
            $code
        );

        self::assertStringContainsString(
            'institutional.academic_cohorts',
            $code
        );
    }

    public function test_preserves_unique_matricula(): void
    {
        $code = self::migration();

        self::assertStringContainsString(
            "text('matricula')->nullable()",
            $code
        );

        self::assertStringContainsString(
            'academic_enrollments_matricula_unique',
            $code
        );

        self::assertStringContainsString(
            'academic_enrollments_confirmation_check',
            $code
        );

        self::assertStringContainsString(
            'academic_enrollments_matricula_nonblank_check',
            $code
        );
    }

    public function test_requires_institutional_executor(): void
    {
        $code = self::migration();

        self::assertStringContainsString(
            'DB::transactionLevel() < 1',
            $code
        );

        self::assertStringContainsString(
            "'siga_migrator'",
            $code
        );

        self::assertStringContainsString(
            "'siga'",
            $code
        );

        self::assertStringContainsString(
            'SET ROLE siga_owner',
            $code
        );

        self::assertStringContainsString(
            'RESET ROLE',
            $code
        );
    }

    public function test_does_not_grant_application_writes(): void
    {
        $code = self::migration();

        self::assertStringContainsString(
            'GRANT SELECT ON TABLE',
            $code
        );

        self::assertStringNotContainsString(
            'GRANT INSERT',
            $code
        );

        self::assertStringNotContainsString(
            'GRANT UPDATE',
            $code
        );

        self::assertStringNotContainsString(
            'GRANT DELETE',
            $code
        );
    }

    public function test_blocks_automatic_rollback(): void
    {
        $code = self::migration();

        self::assertStringContainsString(
            'rollback automatico no autorizado',
            $code
        );

        self::assertStringNotContainsString(
            'Schema::drop',
            $code
        );
    }
}

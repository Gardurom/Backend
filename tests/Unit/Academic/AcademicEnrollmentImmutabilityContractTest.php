<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use PHPUnit\Framework\TestCase;

final class AcademicEnrollmentImmutabilityContractTest
    extends TestCase
{
    private static function proposal(): string
    {
        $path = dirname(__DIR__, 3)
            . '/database/proposals/'
            . 'F3_P02_C1_academic_enrollments.php';

        $code = file_get_contents($path);

        self::assertNotFalse($code);

        return $code;
    }

    public function test_declares_trigger_function(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            'CREATE FUNCTION institutional.protect_academic_enrollment_confirmation()',
            $code
        );

        self::assertStringContainsString(
            'RETURNS trigger',
            $code
        );

        self::assertStringContainsString(
            'SECURITY INVOKER',
            $code
        );
    }

    public function test_trigger_covers_updates_and_deletes(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            'CREATE TRIGGER academic_enrollments_confirmation_immutable',
            $code
        );

        self::assertStringContainsString(
            'BEFORE UPDATE OR DELETE',
            $code
        );

        self::assertStringContainsString(
            'FOR EACH ROW',
            $code
        );
    }

    public function test_rejects_changes_to_confirmed_values(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            'IF OLD.confirmada_at IS NOT NULL AND (',
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

    public function test_rejects_deletion_after_confirmation(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            "IF TG_OP = 'DELETE' THEN",
            $code
        );

        self::assertStringContainsString(
            'no se puede eliminar una incorporacion confirmada',
            $code
        );

        self::assertStringContainsString(
            'RETURN OLD;',
            $code
        );
    }

    public function test_restricts_function_access(): void
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
    }

    public function test_preserves_institutional_safety(): void
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
            'academic_enrollments_matricula_unique',
            $code
        );

        self::assertStringContainsString(
            'academic_enrollments_confirmation_check',
            $code
        );

        self::assertStringNotContainsString(
            'Schema::drop',
            $code
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Academic;

use PHPUnit\Framework\TestCase;

final class AcademicIssuanceStorageContractTest extends TestCase
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

    public function test_declares_three_tables(): void
    {
        $code = self::proposal();

        foreach ([
            'academic_enrollment_sequence_counters',
            'academic_confirmation_operations',
            'academic_enrollment_issuances',
        ] as $table) {
            self::assertStringContainsString(
                "Schema::create(",
                $code
            );

            self::assertStringContainsString($table, $code);
        }
    }

    public function test_counter_scope_is_composite(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            "['id_cohorte', 'tipo_participante']",
            $code
        );

        self::assertStringContainsString(
            'academic_sequence_counters_pk',
            $code
        );

        self::assertStringContainsString(
            'academic_sequence_counter_nonnegative',
            $code
        );
    }

    public function test_operation_has_identity_and_hash(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            "uuid('id_operacion')->primary()",
            $code
        );

        self::assertStringContainsString(
            "char('huella_solicitud', 64)",
            $code
        );

        self::assertStringContainsString(
            'academic_confirmation_hash_check',
            $code
        );

        self::assertStringContainsString(
            'academic_confirmation_mode_check',
            $code
        );
    }

    public function test_issuance_prevents_duplicate_numbers(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            'academic_issuances_scope_sequence_unique',
            $code
        );

        self::assertStringContainsString(
            'academic_issuances_matricula_unique',
            $code
        );

        self::assertStringContainsString(
            'academic_issuance_sequence_positive',
            $code
        );
    }

    public function test_issuance_references_enrollment_scope(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            'academic_issuances_enrollment_fk',
            $code
        );

        self::assertStringContainsString(
            "institutional.academic_enrollments",
            $code
        );

        self::assertStringContainsString(
            'academic_issuances_operation_fk',
            $code
        );
    }

    public function test_protects_confirmed_records(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            'CREATE FUNCTION institutional.reject_academic_issuance_mutation',
            $code
        );

        self::assertStringContainsString(
            'academic_confirmations_immutable',
            $code
        );

        self::assertStringContainsString(
            'academic_issuances_immutable',
            $code
        );

        self::assertStringContainsString(
            'BEFORE UPDATE OR DELETE',
            $code
        );
    }

    public function test_does_not_grant_application_access(): void
    {
        $code = self::proposal();

        self::assertStringContainsString(
            'FROM siga_app',
            $code
        );

        self::assertStringContainsString(
            'FROM PUBLIC',
            $code
        );

        self::assertStringNotContainsString(
            'GRANT SELECT ON TABLE',
            $code
        );

        self::assertStringNotContainsString(
            'GRANT INSERT',
            $code
        );
    }

    public function test_preserves_institutional_guards(): void
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

        self::assertStringContainsString(
            'RESET ROLE',
            $code
        );

        self::assertStringNotContainsString(
            'Schema::drop',
            $code
        );
    }
}

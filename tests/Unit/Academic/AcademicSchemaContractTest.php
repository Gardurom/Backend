<?php

use PHPUnit\Framework\TestCase;

final class AcademicSchemaContractTest extends TestCase
{
    private static function migration(): string
    {
        $path = dirname(__DIR__, 3)
            . '/database/migrations/'
            . '2026_11_09_150000_create_academic_programs_and_cohorts.php';

        $content = file_get_contents($path);

        self::assertNotFalse($content);
        self::assertGreaterThan(1000, strlen($content));

        return $content;
    }

    public function test_declares_both_tables(): void
    {
        $code = self::migration();

        self::assertStringContainsString(
            "Schema::create('institutional.academic_programs'",
            $code
        );

        self::assertStringContainsString(
            "Schema::create('institutional.academic_cohorts'",
            $code
        );
    }

    public function test_declares_uuid_identifiers(): void
    {
        $code = self::migration();

        self::assertStringContainsString(
            "uuid('id_programa')",
            $code
        );

        self::assertStringContainsString(
            "uuid('id_cohorte')",
            $code
        );

        self::assertStringContainsString(
            "uuidv7()",
            $code
        );
    }

    public function test_declares_foreign_key(): void
    {
        $code = self::migration();

        self::assertStringContainsString(
            "references('id_programa')",
            $code
        );

        self::assertStringContainsString(
            "restrictOnDelete()",
            $code
        );

        self::assertStringContainsString(
            "restrictOnUpdate()",
            $code
        );
    }

    public function test_declares_uniqueness(): void
    {
        $code = self::migration();

        self::assertStringContainsString(
            "academic_cohorts_identity_unique",
            $code
        );

        self::assertStringContainsString(
            "string('codigo', 30)->unique()",
            $code
        );
    }

    public function test_declares_domain_checks(): void
    {
        $code = self::migration();

        foreach ([
            'academic_programs_code_check',
            'academic_programs_name_check',
            'academic_cohorts_type_check',
            'academic_cohorts_number_check',
            'academic_cohorts_year_check',
        ] as $constraint) {
            self::assertStringContainsString(
                $constraint,
                $code
            );
        }
    }

    public function test_declares_restricted_access(): void
    {
        $code = self::migration();

        self::assertStringContainsString(
            'SET ROLE siga_owner',
            $code
        );

        self::assertStringContainsString(
            'FROM PUBLIC',
            $code
        );

        self::assertStringContainsString(
            'GRANT SELECT ON TABLE',
            $code
        );

        self::assertStringContainsString(
            'RESET ROLE',
            $code
        );
    }

    public function test_blocks_destructive_rollback(): void
    {
        $code = self::migration();

        self::assertStringContainsString(
            'rollback automatico bloqueado',
            $code
        );

        self::assertStringContainsString(
            'throw new \RuntimeException',
            $code
        );

        self::assertStringNotContainsString(
            "Schema::dropIfExists('institutional.academic_cohorts')",
            $code
        );

        self::assertStringNotContainsString(
            "Schema::dropIfExists('institutional.academic_programs')",
            $code
        );
    }
}
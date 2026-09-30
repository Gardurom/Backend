<?php

namespace Tests\Installation;

use PHPUnit\Framework\Attributes\DataProvider;

class TablePermissionsTest extends InstallationTestCase
{
    #[DataProvider('tablePermissions')]
    public function test_application_has_expected_table_permissions(
        string $table,
        array $expected
    ): void {
        $result = $this->db->selectOne(
            "SELECT
                has_table_privilege(current_user, ?, 'SELECT') AS can_select,
                has_table_privilege(current_user, ?, 'INSERT') AS can_insert,
                has_table_privilege(current_user, ?, 'UPDATE') AS can_update,
                has_table_privilege(current_user, ?, 'DELETE') AS can_delete,
                has_table_privilege(current_user, ?, 'TRUNCATE') AS can_truncate",
            [$table, $table, $table, $table, $table]
        );

        $actual = [
            'SELECT' => $result->can_select,
            'INSERT' => $result->can_insert,
            'UPDATE' => $result->can_update,
            'DELETE' => $result->can_delete,
            'TRUNCATE' => $result->can_truncate,
        ];

        foreach ($expected as $privilege => $allowed) {
            self::assertSame(
                $allowed,
                $actual[$privilege],
                "Permiso {$privilege} inesperado para siga_app sobre {$table}."
            );
        }
    }

    public static function tablePermissions(): iterable
    {
        $operational = [
            'SELECT' => true,
            'INSERT' => true,
            'UPDATE' => true,
            'DELETE' => true,
            'TRUNCATE' => false,
        ];

        foreach ([
            'system.users',
            'system.password_reset_tokens',
            'system.sessions',
            'system.cache',
            'system.cache_locks',
            'system.jobs',
            'system.job_batches',
            'system.failed_jobs',
        ] as $table) {
            yield $table => [$table, $operational];
        }

        $readOnly = [
            'SELECT' => true,
            'INSERT' => false,
            'UPDATE' => false,
            'DELETE' => false,
            'TRUNCATE' => false,
        ];

        foreach ([
            'institutional.countries',
            'institutional.territories',
        ] as $table) {
            yield $table => [$table, $readOnly];
        }

        yield 'institutional.persons' => [
            'institutional.persons',
            [
                'SELECT' => true,
                'INSERT' => true,
                'UPDATE' => true,
                'DELETE' => false,
                'TRUNCATE' => false,
            ],
        ];

        $noAccess = [
            'SELECT' => false,
            'INSERT' => false,
            'UPDATE' => false,
            'DELETE' => false,
            'TRUNCATE' => false,
        ];

        foreach ([
            'system.counters',
            'system.migrations',
        ] as $table) {
            yield $table => [$table, $noAccess];
        }
    }
}
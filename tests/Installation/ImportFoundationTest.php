<?php

namespace Tests\Installation;

class ImportFoundationTest extends InstallationTestCase
{
    public function test_person_import_permission_exists_and_is_allowed_for_person_manager(): void
    {
        $permission = $this->db->selectOne(
            "SELECT code, name
             FROM system.permissions
             WHERE code = 'personas.importar'"
        );

        self::assertNotNull(
            $permission,
            'Debe existir el permiso personas.importar.'
        );

        self::assertSame(
            'Importar personas',
            $permission->name
        );

        $relationship = $this->db->selectOne(
            "SELECT rp.effect
             FROM system.role_permissions AS rp
             JOIN system.roles AS r
               ON r.id = rp.role_id
             JOIN system.permissions AS p
               ON p.id = rp.permission_id
             WHERE r.code = 'ROL_GESTOR_PERSONAS'
               AND p.code = 'personas.importar'"
        );

        self::assertNotNull(
            $relationship,
            'ROL_GESTOR_PERSONAS debe recibir personas.importar.'
        );

        self::assertSame(
            'ALLOW',
            $relationship->effect
        );
    }

    public function test_import_batches_table_has_expected_foundation(): void
    {
        self::assertTrue(
            $this->tableExists('import_batches'),
            'Debe existir system.import_batches.'
        );

        self::assertSame(
            [
                'id',
                'uuid',
                'import_type',
                'status',
                'original_filename',
                'storage_disk',
                'storage_path',
                'file_sha256',
                'options',
                'total_rows',
                'valid_rows',
                'invalid_rows',
                'processed_rows',
                'failed_rows',
                'created_by',
                'confirmed_at',
                'started_at',
                'finished_at',
                'created_at',
                'updated_at',
            ],
            $this->columnNames('import_batches')
        );

        self::assertSame(
            'uuid',
            $this->columnDataType(
                'import_batches',
                'uuid'
            )
        );

        self::assertSame(
            'jsonb',
            $this->columnDataType(
                'import_batches',
                'options'
            )
        );

        foreach ([
            'confirmed_at',
            'started_at',
            'finished_at',
        ] as $column) {
            self::assertSame(
                'timestamp with time zone',
                $this->columnDataType(
                    'import_batches',
                    $column
                )
            );
        }

        $uuidUnique = $this->constraintDefinition(
            'import_batches',
            'import_batches_uuid_unique'
        );

        self::assertNotNull(
            $uuidUnique,
            'import_batches.uuid debe ser único.'
        );

        $createdBy = $this->constraintDefinition(
            'import_batches',
            'import_batches_created_by_fk'
        );

        self::assertNotNull(
            $createdBy,
            'import_batches.created_by debe referenciar system.users.'
        );

        self::assertStringContainsString(
            'FOREIGN KEY (created_by)',
            $createdBy
        );

        self::assertStringContainsString(
            'REFERENCES system.users(id)',
            $createdBy
        );

        $status = $this->constraintDefinition(
            'import_batches',
            'import_batches_status_check'
        );

        self::assertNotNull(
            $status,
            'import_batches debe restringir sus estados.'
        );

        foreach ([
            'UPLOADED',
            'VALIDATING',
            'READY',
            'QUEUED',
            'PROCESSING',
            'COMPLETED',
            'FAILED',
            'CANCELLED',
        ] as $expectedStatus) {
            self::assertStringContainsString(
                $expectedStatus,
                $status
            );
        }

        $this->assertImportTablePrivileges(
            'system.import_batches'
        );

        $this->assertSequenceUsage(
            'system.import_batches_id_seq'
        );
    }

    public function test_import_rows_table_has_expected_foundation(): void
    {
        self::assertTrue(
            $this->tableExists('import_rows'),
            'Debe existir system.import_rows.'
        );

        self::assertSame(
            [
                'id',
                'import_batch_id',
                'row_number',
                'status',
                'raw_data',
                'normalized_data',
                'validation_errors',
                'result_data',
                'created_at',
                'updated_at',
            ],
            $this->columnNames('import_rows')
        );

        foreach ([
            'raw_data',
            'normalized_data',
            'validation_errors',
            'result_data',
        ] as $column) {
            self::assertSame(
                'jsonb',
                $this->columnDataType(
                    'import_rows',
                    $column
                )
            );
        }

        $batchForeignKey = $this->constraintDefinition(
            'import_rows',
            'import_rows_batch_fk'
        );

        self::assertNotNull(
            $batchForeignKey,
            'import_rows debe pertenecer a import_batches.'
        );

        self::assertStringContainsString(
            'FOREIGN KEY (import_batch_id)',
            $batchForeignKey
        );

        self::assertStringContainsString(
            'REFERENCES system.import_batches(id)',
            $batchForeignKey
        );

        $batchRowUnique = $this->constraintDefinition(
            'import_rows',
            'import_rows_batch_row_unique'
        );

        self::assertNotNull(
            $batchRowUnique,
            'Una fila solo puede existir una vez dentro de un lote.'
        );

        $status = $this->constraintDefinition(
            'import_rows',
            'import_rows_status_check'
        );

        self::assertNotNull(
            $status,
            'import_rows debe restringir sus estados.'
        );

        foreach ([
            'PENDING',
            'VALID',
            'INVALID',
            'PROCESSED',
            'FAILED',
            'SKIPPED',
        ] as $expectedStatus) {
            self::assertStringContainsString(
                $expectedStatus,
                $status
            );
        }

        $this->assertImportTablePrivileges(
            'system.import_rows'
        );

        $this->assertSequenceUsage(
            'system.import_rows_id_seq'
        );
    }

    private function tableExists(
        string $table
    ): bool {
        $result = $this->db->selectOne(
            "SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'system'
                  AND table_name = ?
            ) AS exists",
            [$table]
        );

        return (bool) $result->exists;
    }

    /**
     * @return array<int, string>
     */
    private function columnNames(
        string $table
    ): array {
        $columns = $this->db->select(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = 'system'
               AND table_name = ?
             ORDER BY ordinal_position",
            [$table]
        );

        return array_map(
            fn ($column) => $column->column_name,
            $columns
        );
    }

    private function columnDataType(
        string $table,
        string $column
    ): ?string {
        $result = $this->db->selectOne(
            "SELECT data_type
             FROM information_schema.columns
             WHERE table_schema = 'system'
               AND table_name = ?
               AND column_name = ?",
            [
                $table,
                $column,
            ]
        );

        return $result?->data_type;
    }

    private function constraintDefinition(
        string $table,
        string $constraint
    ): ?string {
        $result = $this->db->selectOne(
            "SELECT pg_get_constraintdef(c.oid) AS definition
             FROM pg_catalog.pg_constraint AS c
             JOIN pg_catalog.pg_class AS t
               ON t.oid = c.conrelid
             JOIN pg_catalog.pg_namespace AS n
               ON n.oid = t.relnamespace
             WHERE n.nspname = 'system'
               AND t.relname = ?
               AND c.conname = ?",
            [
                $table,
                $constraint,
            ]
        );

        return $result?->definition;
    }

    private function assertImportTablePrivileges(
        string $table
    ): void {
        $privileges = $this->db->selectOne(
            "SELECT
                has_table_privilege(
                    current_user,
                    ?,
                    'SELECT'
                ) AS can_select,
                has_table_privilege(
                    current_user,
                    ?,
                    'INSERT'
                ) AS can_insert,
                has_table_privilege(
                    current_user,
                    ?,
                    'UPDATE'
                ) AS can_update,
                has_table_privilege(
                    current_user,
                    ?,
                    'DELETE'
                ) AS can_delete,
                has_table_privilege(
                    current_user,
                    ?,
                    'TRUNCATE'
                ) AS can_truncate",
            [
                $table,
                $table,
                $table,
                $table,
                $table,
            ]
        );

        self::assertTrue(
            (bool) $privileges->can_select
        );

        self::assertTrue(
            (bool) $privileges->can_insert
        );

        self::assertTrue(
            (bool) $privileges->can_update
        );

        self::assertFalse(
            (bool) $privileges->can_delete
        );

        self::assertFalse(
            (bool) $privileges->can_truncate
        );
    }

    private function assertSequenceUsage(
        string $sequence
    ): void {
        $result = $this->db->selectOne(
            "SELECT has_sequence_privilege(
                current_user,
                ?,
                'USAGE'
            ) AS can_use",
            [$sequence]
        );

        self::assertTrue(
            (bool) $result->can_use,
            "siga_app debe tener USAGE sobre {$sequence}."
        );
    }
}

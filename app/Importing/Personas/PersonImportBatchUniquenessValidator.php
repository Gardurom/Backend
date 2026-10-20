<?php

namespace App\Importing\Personas;

use App\Importing\ImportRowStatus;
use Illuminate\Support\Facades\DB;

final class PersonImportBatchUniquenessValidator
{
    /**
     * @var array<string, array{
     *     person_comparison: string,
     *     existing: string,
     *     duplicate: string
     * }>
     */
    private const FIELDS = [
        'curp' => [
            'person_comparison' => 'p.curp = CAST(c.value AS character(18))',
            'existing' => 'La CURP ya está registrada en otra persona.',
            'duplicate' => 'La CURP está duplicada dentro del archivo.',
        ],
        'rfc' => [
            'person_comparison' => 'p.rfc = CAST(c.value AS character(13))',
            'existing' => 'El RFC ya está registrado en otra persona.',
            'duplicate' => 'El RFC está duplicado dentro del archivo.',
        ],
        'correo_institucional' => [
            'person_comparison' => 'lower(p.correo_institucional) = c.value',
            'existing' => 'El correo institucional ya está registrado en otra persona.',
            'duplicate' => 'El correo institucional está duplicado dentro del archivo.',
        ],
    ];

    public function validate(
        int $batchId
    ): void {
        DB::connection()->transaction(
            function () use ($batchId): void {
                foreach (
                    self::FIELDS as $field => $configuration
                ) {
                    $this->validateField(
                        $batchId,
                        $field,
                        $configuration
                    );
                }
            }
        );
    }

    /**
     * @param  array{
     *     person_comparison: string,
     *     existing: string,
     *     duplicate: string
     * }  $configuration
     */
    private function validateField(
        int $batchId,
        string $field,
        array $configuration
    ): void {
        $valueExpression =
            "r.normalized_data ->> '{$field}'";

        $validationErrorsExpression = <<<'SQL'
            CASE
                WHEN jsonb_typeof(r.validation_errors) = 'object'
                THEN r.validation_errors
                ELSE '{}'::jsonb
            END
            SQL;

        $personComparison =
            $configuration['person_comparison'];

        $sql = <<<SQL
            WITH candidates AS (
                SELECT
                    r.id,
                    {$valueExpression} AS value
                FROM system.import_rows AS r
                WHERE
                    r.import_batch_id = ?
                    AND r.status IN (?, ?)
                    AND NOT jsonb_exists(
                        {$validationErrorsExpression},
                        ?
                    )
                    AND NULLIF(
                        {$valueExpression},
                        ''
                    ) IS NOT NULL
            ),
            duplicate_values AS (
                SELECT
                    value
                FROM candidates
                GROUP BY value
                HAVING COUNT(*) > 1
            ),
            conflicts AS (
                SELECT
                    c.id,
                    d.value IS NOT NULL
                        AS duplicated_in_batch,
                    EXISTS (
                        SELECT 1
                        FROM institutional.persons AS p
                        WHERE {$personComparison}
                    ) AS exists_in_persons
                FROM candidates AS c
                LEFT JOIN duplicate_values AS d
                    ON d.value = c.value
            ),
            affected AS (
                SELECT
                    id,
                    array_remove(
                        ARRAY[
                            CASE
                                WHEN duplicated_in_batch
                                THEN CAST(? AS text)
                                ELSE NULL
                            END,
                            CASE
                                WHEN exists_in_persons
                                THEN CAST(? AS text)
                                ELSE NULL
                            END
                        ],
                        NULL
                    ) AS messages
                FROM conflicts
                WHERE
                    duplicated_in_batch
                    OR exists_in_persons
            )
            UPDATE system.import_rows AS r
            SET
                status = ?,
                validation_errors = jsonb_set(
                    {$validationErrorsExpression},
                    ARRAY[
                        CAST(? AS text)
                    ],
                    to_jsonb(
                        a.messages
                    ),
                    true
                ),
                updated_at = CURRENT_TIMESTAMP
            FROM affected AS a
            WHERE
                r.id = a.id
                AND r.import_batch_id = ?
            SQL;

        DB::connection()->update(
            $sql,
            [
                $batchId,
                ImportRowStatus::VALID->value,
                ImportRowStatus::INVALID->value,
                $field,
                $configuration['duplicate'],
                $configuration['existing'],
                ImportRowStatus::INVALID->value,
                $field,
                $batchId,
            ]
        );
    }
}

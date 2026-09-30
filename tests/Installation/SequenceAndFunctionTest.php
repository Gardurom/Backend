<?php

namespace Tests\Installation;

use PHPUnit\Framework\Attributes\DataProvider;

class SequenceAndFunctionTest extends InstallationTestCase
{
    #[DataProvider('sequences')]
    public function test_application_can_use_but_not_reset_sequence(
        string $sequence
    ): void {
        $result = $this->db->selectOne(
            "SELECT
                has_sequence_privilege(current_user, ?, 'USAGE') AS can_use,
                has_sequence_privilege(current_user, ?, 'UPDATE') AS can_reset",
            [$sequence, $sequence]
        );

        self::assertTrue(
            $result->can_use,
            "siga_app necesita USAGE sobre {$sequence}."
        );

        self::assertFalse(
            $result->can_reset,
            "siga_app no debe poder cambiar el valor mediante setval en {$sequence}."
        );
    }

    public static function sequences(): iterable
    {
        yield 'usuarios' => ['system.users_id_seq'];
        yield 'trabajos' => ['system.jobs_id_seq'];
        yield 'trabajos fallidos' => ['system.failed_jobs_id_seq'];
    }

    public function test_expediente_function_has_expected_security(): void
    {
        $result = $this->db->selectOne(
            "SELECT
                pg_get_userbyid(p.proowner) AS owner_name,
                p.prosecdef AS security_definer,
                array_to_json(p.proconfig)::TEXT AS settings,
                has_function_privilege(
                    current_user, p.oid, 'EXECUTE'
                ) AS can_execute,
                EXISTS (
                    SELECT 1
                    FROM pg_catalog.aclexplode(
                        COALESCE(
                            p.proacl,
                            pg_catalog.acldefault('f', p.proowner)
                        )
                    ) AS permission
                    WHERE permission.grantee = 0
                      AND permission.privilege_type = 'EXECUTE'
                ) AS public_can_execute
             FROM pg_catalog.pg_proc AS p
             WHERE p.oid = to_regprocedure(
                 'system.next_expediente_number()'
             )"
        );

        self::assertNotNull(
            $result,
            'Debe existir system.next_expediente_number().'
        );

        self::assertSame(
            'siga_owner',
            $result->owner_name,
            'La funcion debe pertenecer a siga_owner.'
        );

        self::assertTrue(
            $result->security_definer,
            'La funcion debe utilizar SECURITY DEFINER.'
        );

        self::assertSame(
            ['search_path=pg_catalog, pg_temp'],
            json_decode(
                $result->settings ?? 'null',
                true,
                512,
                JSON_THROW_ON_ERROR
            ),
            'La funcion debe conservar la ruta de busqueda restringida.'
        );

        self::assertTrue(
            $result->can_execute,
            'siga_app debe poder ejecutar la funcion.'
        );

        self::assertFalse(
            $result->public_can_execute,
            'PUBLIC no debe tener permiso de ejecucion sobre la funcion.'
        );
    }
}
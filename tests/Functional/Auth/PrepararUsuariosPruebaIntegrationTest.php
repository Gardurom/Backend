<?php

namespace Tests\Functional\Auth;

use App\Actions\User\CatalogoUsuariosPrueba;
use App\Actions\User\PrepararUsuariosPrueba;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\Functional\FunctionalTestCase;

class PrepararUsuariosPruebaIntegrationTest extends FunctionalTestCase
{
    public function test_modelos_y_facade_comparten_transaccion_postgresql(): void
    {
        $conexionPrincipal = $this->db;
        $conexionFacade = DB::connection();
        $conexionUsuarios = (new User)->getConnection();
        $conexionRoles = (new Role)->getConnection();
        $conexionRelacion = (new User)->roles()->getQuery()
            ->getModel()
            ->getConnection();

        self::assertSame(
            'pgsql',
            $conexionPrincipal->getDriverName()
        );

        self::assertGreaterThan(
            0,
            $conexionPrincipal->transactionLevel()
        );

        self::assertSame(
            $conexionPrincipal,
            $conexionFacade
        );

        self::assertSame(
            $conexionPrincipal,
            $conexionUsuarios
        );

        self::assertSame(
            $conexionPrincipal,
            $conexionRoles
        );

        self::assertSame(
            $conexionPrincipal,
            $conexionRelacion
        );
    }
    public function test_transaccion_anidada_conserva_transaccion_externa(): void
    {
        $nivelInicial = $this->db->transactionLevel();

        self::assertSame(1, $nivelInicial);

        $resultado = $this->db->transaction(function (): string {
            self::assertSame(
                2,
                $this->db->transactionLevel()
            );

            return 'TRANSACCION_INTERNA_COMPLETADA';
        });

        self::assertSame(
            'TRANSACCION_INTERNA_COMPLETADA',
            $resultado
        );

        self::assertSame(
            $nivelInicial,
            $this->db->transactionLevel(),
            'La transacción externa debe permanecer abierta.'
        );
    }
    public function test_rollback_revierte_insercion_temporal(): void
    {
        $correo = 'rollback.' . bin2hex(random_bytes(12))
            . '@ejemplo.test';

        $nivelInicial = $this->db->transactionLevel();

        self::assertSame(1, $nivelInicial);

        self::assertFalse(
            $this->db->table('system.users')
                ->where('email', $correo)
                ->exists()
        );

        $this->db->beginTransaction();

        try {
            self::assertSame(
                2,
                $this->db->transactionLevel()
            );

            $this->db->table('system.users')->insert([
                'id_persona' => null,
                'name' => 'PRUEBA TEMPORAL ROLLBACK',
                'email' => $correo,
                'password' => 'NO_ES_UNA_CREDENCIAL_DE_ACCESO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            self::assertTrue(
                $this->db->table('system.users')
                    ->where('email', $correo)
                    ->exists()
            );
        } finally {
            if ($this->db->transactionLevel() > $nivelInicial) {
                $this->db->rollBack($nivelInicial);
            }
        }

        self::assertSame(
            $nivelInicial,
            $this->db->transactionLevel()
        );

        self::assertFalse(
            $this->db->table('system.users')
                ->where('email', $correo)
                ->exists(),
            'El registro debe desaparecer tras el rollback.'
        );
    }
    public function test_creacion_de_seis_cuentas_es_reversible(): void
    {
        self::assertSame('installation', $this->app->environment());
        self::assertSame('pgsql', $this->db->getDriverName());
        self::assertSame(1, $this->db->transactionLevel());

        $correos = CatalogoUsuariosPrueba::correos();

        self::assertSame(
            0,
            $this->db->table('system.users')
                ->whereIn('email', $correos)
                ->count()
        );

        self::assertSame(
            4,
            $this->db->table('system.roles')
                ->whereIn(
                    'code',
                    CatalogoUsuariosPrueba::ROLES_REQUERIDOS
                )
                ->count()
        );

        $anterior = getenv('SIGA_PERMITIR_USUARIOS_PRUEBA');
        $password = 'ClaveTemporalIntegracionSegura2026!';
        $idsCreados = [];

        // Savepoint adicional dentro de la transacción de PHPUnit.
        $this->db->beginTransaction();

        try {
            self::assertSame(2, $this->db->transactionLevel());

            $this->app->detectEnvironment(fn () => 'local');
            putenv('SIGA_PERMITIR_USUARIOS_PRUEBA=1');

            $resultado = (new PrepararUsuariosPrueba)
                ->ejecutar($password);

            self::assertSame(2, $this->db->transactionLevel());
            self::assertCount(6, $resultado);

            $idsCreados = array_column($resultado, 'id');

            self::assertCount(
                6,
                array_unique($idsCreados),
                'Los seis identificadores deben ser diferentes.'
            );

            foreach (CatalogoUsuariosPrueba::CUENTAS as $email => $datos) {
                $usuario = \App\Models\User::query()
                    ->where('email', $email)
                    ->firstOrFail();

                self::assertSame($datos['nombre'], $usuario->name);
                self::assertNull($usuario->id_persona);

                self::assertNotSame($password, $usuario->password);
                self::assertTrue(
                    Hash::check($password, $usuario->password)
                );

                $rolesReales = $usuario->roles()
                    ->pluck('code')
                    ->all();

                self::assertEqualsCanonicalizing(
                    $datos['roles'],
                    $rolesReales
                );
            }
        } finally {
            // Restaurar siempre la habilitación temporal.
            if ($anterior === false) {
                putenv('SIGA_PERMITIR_USUARIOS_PRUEBA');
            } else {
                putenv('SIGA_PERMITIR_USUARIOS_PRUEBA=' . $anterior);
            }

            $this->app->detectEnvironment(fn () => 'installation');

            // La transacción externa nunca debe haberse cerrado.
            $nivelActual = $this->db->transactionLevel();

            if ($nivelActual < 1) {
                throw new \RuntimeException(
                    'Se perdió la transacción externa de PHPUnit.'
                );
            }

            // Revertir los cambios hasta conservar el nivel externo.
            if ($nivelActual > 1) {
                $this->db->rollBack(1);
            }

            self::assertSame(
                1,
                $this->db->transactionLevel(),
                'La transacción externa debe permanecer abierta.'
            );
        }

        self::assertSame('installation', $this->app->environment());
        self::assertSame(1, $this->db->transactionLevel());

        self::assertSame(
            0,
            $this->db->table('system.users')
                ->whereIn('email', $correos)
                ->count(),
            'Ninguna cuenta debe permanecer después del rollback.'
        );
        self::assertCount(6, $idsCreados);

        self::assertSame(
            0,
            $this->db->table('system.user_roles')
                ->whereIn('user_id', $idsCreados)
                ->count(),
            'No deben permanecer relaciones de los usuarios creados.'
        );
    }
    public function test_rechaza_creacion_en_entorno_installation(): void
    {
        $anterior = getenv('SIGA_PERMITIR_USUARIOS_PRUEBA');

        putenv('SIGA_PERMITIR_USUARIOS_PRUEBA=1');

        try {
            self::assertSame(
                'installation',
                $this->app->environment()
            );

            self::assertSame(
                'pgsql',
                $this->db->getDriverName()
            );

            self::assertGreaterThan(
                0,
                $this->db->transactionLevel()
            );

            $cuentasAntes = $this->db
                ->table('system.users')
                ->whereIn('email', CatalogoUsuariosPrueba::correos())
                ->count();

            self::assertSame(0, $cuentasAntes);

            $rechazado = false;

            try {
                (new PrepararUsuariosPrueba)->ejecutar(
                    'ClaveTemporalExclusivaDePruebas123!'
                );
            } catch (LogicException $error) {
                self::assertSame(
                    'La preparación solo está autorizada en desarrollo local.',
                    $error->getMessage()
                );

                $rechazado = true;
            }

            self::assertTrue(
                $rechazado,
                'La acción debió rechazar el entorno installation.'
            );

            $cuentasDespues = $this->db
                ->table('system.users')
                ->whereIn('email', CatalogoUsuariosPrueba::correos())
                ->count();

            self::assertSame(
                $cuentasAntes,
                $cuentasDespues,
                'El rechazo no debe modificar las cuentas.'
            );
        } finally {
            if ($anterior === false) {
                putenv('SIGA_PERMITIR_USUARIOS_PRUEBA');
            } else {
                putenv(
                    'SIGA_PERMITIR_USUARIOS_PRUEBA=' . $anterior
                );
            }
        }
    }
}
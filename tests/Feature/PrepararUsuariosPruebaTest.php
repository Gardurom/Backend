<?php

namespace Tests\Feature;

use App\Actions\User\PrepararUsuariosPrueba;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class PrepararUsuariosPruebaTest extends TestCase
{
    public function test_rechaza_creacion_sin_habilitacion(): void
    {
        $anterior = getenv('SIGA_PERMITIR_USUARIOS_PRUEBA');

        putenv('SIGA_PERMITIR_USUARIOS_PRUEBA');

        try {
            $this->app->detectEnvironment(fn () => 'local');
            config()->set('database.default', 'sqlite');

            DB::shouldReceive('selectOne')->never();
            DB::shouldReceive('transaction')->never();

            $this->expectException(LogicException::class);
            $this->expectExceptionMessage(
                'La preparación de usuarios de prueba no está habilitada.'
            );

            (new PrepararUsuariosPrueba)->ejecutar(
                'ClaveUnicamenteParaPruebas123!'
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

    public function test_rechaza_ambiente_de_produccion(): void
    {
        $anterior = getenv('SIGA_PERMITIR_USUARIOS_PRUEBA');

        putenv('SIGA_PERMITIR_USUARIOS_PRUEBA=1');

        try {
            $this->app->detectEnvironment(fn () => 'production');
            config()->set('database.default', 'sqlite');

            DB::shouldReceive('selectOne')->never();
            DB::shouldReceive('transaction')->never();

            $this->expectException(LogicException::class);
            $this->expectExceptionMessage(
                'La preparación solo está autorizada en desarrollo local.'
            );

            (new PrepararUsuariosPrueba)->ejecutar(
                'ClaveUnicamenteParaPruebas123!'
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

    public function test_rechaza_conexion_sqlite(): void
    {
        $anterior = getenv('SIGA_PERMITIR_USUARIOS_PRUEBA');

        putenv('SIGA_PERMITIR_USUARIOS_PRUEBA=1');

        try {
            $this->app->detectEnvironment(fn () => 'local');
            config()->set('database.default', 'sqlite');

            DB::shouldReceive('selectOne')->never();
            DB::shouldReceive('transaction')->never();

            $this->expectException(LogicException::class);

            (new PrepararUsuariosPrueba)->ejecutar(
                'ClaveUnicamenteParaPruebas123!'
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

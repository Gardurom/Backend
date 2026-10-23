<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class VerificarUsuariosPruebaCommandTest extends TestCase
{
    public function test_rechaza_ejecucion_sin_opcion_verificar(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        config()->set('database.default', 'pgsql');

        $resultado = Artisan::call('siga:usuarios-prueba');

        self::assertSame(1, $resultado);
    }

    public function test_rechaza_ejecucion_en_produccion(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        config()->set('database.default', 'pgsql');

        $resultado = Artisan::call(
            'siga:usuarios-prueba',
            ['--verificar' => true]
        );

        self::assertSame(1, $resultado);
    }

    public function test_rechaza_conexion_distinta_de_postgresql(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        config()->set('database.default', 'sqlite');

        $resultado = Artisan::call(
            'siga:usuarios-prueba',
            ['--verificar' => true]
        );

        self::assertSame(1, $resultado);
    }
}

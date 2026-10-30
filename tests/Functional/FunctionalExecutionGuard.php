<?php

declare(strict_types=1);

namespace Tests\Functional;

/**
 * Bloqueo temporal de seguridad.
 *
 * Las pruebas funcionales actuales estan configuradas para escribir
 * sobre la base institucional siga.
 *
 * No habilitar su ejecucion hasta implantar y validar un entorno
 * PostgreSQL independiente de la base institucional.
 */
final class FunctionalExecutionGuard
{
    public static function assertSafeToRun(): void
    {
        throw new \RuntimeException(
            'SIGA_FUNCTIONAL_EXECUTION_BLOCKED: '
            . 'Las pruebas funcionales de escritura permanecen bloqueadas '
            . 'hasta disponer de un entorno de integracion aislado.'
        );
    }
}
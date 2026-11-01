<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
/*
 * SIGA F5.6: verificacion operativa de solo lectura.
 * No consulta ni registra informacion personal.
 */
\Illuminate\Support\Facades\Artisan::command(
    'siga:verificar-operacion',
    function (): int {
        $controles = [
            'conexion' => 'SELECT 1',
            'usuarios' => 'SELECT 1 FROM system.users LIMIT 1',
            'sesiones' => 'SELECT 1 FROM system.sessions LIMIT 1',
            'auditoria' => 'SELECT 1 FROM system.activities LIMIT 1',
        ];

        $fallos = [];

        foreach ($controles as $nombre => $consulta) {
            try {
                \Illuminate\Support\Facades\DB::select($consulta);
                $this->info('[OK] '.$nombre);
            } catch (\Throwable $error) {
                $fallos[] = $nombre;
                $this->error('[ERROR] '.$nombre);
            }
        }

        if ($fallos !== []) {
            \Illuminate\Support\Facades\Log::error(
                'SIGA_MONITOREO_OPERATIVO_ERROR',
                ['controles' => $fallos]
            );

            return 1;
        }

        \Illuminate\Support\Facades\Log::info(
            'SIGA_MONITOREO_OPERATIVO_OK',
            ['controles_verificados' => count($controles)]
        );

        $this->info('SIGA_MONITOREO_OPERATIVO_VERIFICADO');

        return 0;
    }
)->purpose('Verificar conexion, usuarios, sesiones y auditoria de SIGA');
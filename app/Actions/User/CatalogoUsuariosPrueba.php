<?php

namespace App\Actions\User;

final class CatalogoUsuariosPrueba
{
    public const CUENTAS = [
        'prueba.admin@ejemplo.test' => [
            'nombre' => 'PRUEBA ADMINISTRADOR',
            'roles' => ['ROL_ADMIN_SISTEMA'],
        ],
        'prueba.gestor@ejemplo.test' => [
            'nombre' => 'PRUEBA GESTOR PERSONAS',
            'roles' => ['ROL_GESTOR_PERSONAS'],
        ],
        'prueba.consulta@ejemplo.test' => [
            'nombre' => 'PRUEBA CONSULTA PERSONAS',
            'roles' => ['ROL_CONSULTA_PERSONAS'],
        ],
        'prueba.auditor@ejemplo.test' => [
            'nombre' => 'PRUEBA AUDITOR',
            'roles' => ['ROL_AUDITOR'],
        ],
        'prueba.sinrol@ejemplo.test' => [
            'nombre' => 'PRUEBA SIN ROL',
            'roles' => [],
        ],
        'prueba.multirrol@ejemplo.test' => [
            'nombre' => 'PRUEBA MULTIRROL',
            'roles' => [
                'ROL_CONSULTA_PERSONAS',
                'ROL_GESTOR_PERSONAS',
            ],
        ],
    ];

    public const ROLES_REQUERIDOS = [
        'ROL_ADMIN_SISTEMA',
        'ROL_GESTOR_PERSONAS',
        'ROL_CONSULTA_PERSONAS',
        'ROL_AUDITOR',
    ];

    public static function correos(): array
    {
        return array_keys(self::CUENTAS);
    }
}

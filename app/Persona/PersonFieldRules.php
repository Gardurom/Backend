<?php

namespace App\Persona;

final class PersonFieldRules
{
    /**
     * Reglas reutilizables de los campos de dominio de Persona.
     *
     * No define qué flujo puede modificar cada campo.
     * Esa responsabilidad pertenece a cada frontera de entrada.
     *
     * @return array<string, list<string>>
     */
    public static function all(): array
    {
        return [
            'curp' => [
                'nullable',
                'string',
                'size:18',
            ],
            'rfc' => [
                'nullable',
                'string',
                'size:13',
            ],
            'nombres' => [
                'string',
                'max:150',
            ],
            'apellido_paterno' => [
                'nullable',
                'string',
                'max:100',
            ],
            'apellido_materno' => [
                'nullable',
                'string',
                'max:100',
            ],
            'fecha_nacimiento' => [
                'nullable',
                'date',
                'before_or_equal:'
                    .now('America/Mexico_City')
                        ->toDateString(),
            ],
            'sexo' => [
                'nullable',
                'string',
                'in:MASCULINO,FEMENINO',
            ],
            'estado_civil' => [
                'nullable',
                'string',
                'in:SOLTERO,CASADO',
            ],
            'correo_institucional' => [
                'nullable',
                'string',
                'max:254',
            ],
            'correo_personal' => [
                'nullable',
                'string',
                'max:254',
            ],
        ];
    }
}

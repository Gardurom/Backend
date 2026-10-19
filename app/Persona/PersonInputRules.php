<?php

namespace App\Persona;

final class PersonInputRules
{
    /**
     * Reglas para registrar una Persona.
     *
     * @return array<string, list<string>>
     */
    public static function forCreate(): array
    {
        $rules = self::base();

        $rules['nombres'] = [
            'required',
            ...$rules['nombres'],
        ];

        $rules['apellido_paterno'][] =
            'required_without:apellido_materno';

        $rules['apellido_materno'][] =
            'required_without:apellido_paterno';

        return $rules;
    }

    /**
     * Reglas para actualizar parcialmente una Persona.
     *
     * @return array<string, list<string>>
     */
    public static function forUpdate(): array
    {
        return array_map(
            static fn (array $rules): array => [
                'sometimes',
                ...$rules,
            ],
            self::base()
        );
    }

    /**
     * Campos actualmente admitidos por la entrada de Persona.
     *
     * No incluir aquí campos del sistema ni campos que todavía
     * no formen parte de un flujo autorizado.
     *
     * @return array<string, list<string>>
     */
    private static function base(): array
    {
        return [
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
            'fecha_nacimiento' => [
                'nullable',
                'date',
                'before_or_equal:'
                    .now('America/Mexico_City')
                        ->toDateString(),
            ],
            'correo_personal' => [
                'nullable',
                'string',
                'max:254',
            ],
        ];
    }
}

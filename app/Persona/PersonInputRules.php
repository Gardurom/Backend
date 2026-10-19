<?php

namespace App\Persona;

final class PersonInputRules
{
    /**
     * Campos actualmente habilitados por el API de Persona.
     *
     * @var list<string>
     */
    private const API_FIELDS = [
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'sexo',
        'estado_civil',
        'fecha_nacimiento',
        'correo_personal',
    ];

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
     * Selecciona únicamente los campos autorizados por el API.
     *
     * @return array<string, list<string>>
     */
    private static function base(): array
    {
        $fieldRules =
            PersonFieldRules::all();

        $rules = [];

        foreach (self::API_FIELDS as $field) {
            $rules[$field] =
                $fieldRules[$field];
        }

        return $rules;
    }
}

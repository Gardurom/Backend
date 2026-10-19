<?php

namespace App\Importing\Personas;

use App\Persona\PersonFieldRules;
use Illuminate\Contracts\Validation\Factory;

final class PersonImportRowValidator
{
    /**
     * Campos de Persona admitidos por la importación v2.
     *
     * Esta lista es deliberadamente explícita para impedir
     * que futuros campos de PersonFieldRules se habiliten
     * automáticamente en una plantilla ya versionada.
     *
     * @var list<string>
     */
    private const PERSON_FIELDS = [
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'curp',
        'rfc',
        'fecha_nacimiento',
        'sexo',
        'estado_civil',
        'correo_institucional',
        'correo_personal',
    ];

    public function __construct(
        private readonly Factory $validatorFactory,
        private readonly PersonImportTemplateV2 $template
    ) {}

    /**
     * @param  array<string, mixed>  $rawData
     */
    public function validate(
        array $rawData
    ): PersonImportRowValidationResult {
        $normalizedData =
            $this->template->normalizeStaged(
                $rawData
            );

        $validator =
            $this->validatorFactory->make(
                $normalizedData,
                $this->rules()
            );

        $validator->passes();

        return new PersonImportRowValidationResult(
            normalizedData: $normalizedData,
            errors: $validator
                ->errors()
                ->toArray()
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function rules(): array
    {
        $fieldRules =
            PersonFieldRules::all();

        $rules = [];

        foreach (
            self::PERSON_FIELDS as $field
        ) {
            $rules[$field] =
                $fieldRules[$field];
        }

        $rules['nombres'] = [
            'required',
            ...$rules['nombres'],
        ];

        $rules['apellido_paterno'][] =
            'required_without:apellido_materno';

        $rules['apellido_materno'][] =
            'required_without:apellido_paterno';

        foreach ([
            'curp',
            'rfc',
            'correo_institucional',
            'correo_personal',
        ] as $field) {
            $rules[$field][] =
                'not_regex:/\s/';
        }

        $rules['pais_origen_alpha3'] = [
            'nullable',
            'string',
            'size:3',
            'regex:/^[A-Z]{3}$/',
        ];

        $rules['pais_nacimiento_alpha3'] = [
            'required_with:territorio_nacimiento_clave',
            'nullable',
            'string',
            'size:3',
            'regex:/^[A-Z]{3}$/',
        ];

        $rules['territorio_nacimiento_clave'] = [
            'nullable',
            'string',
            'max:20',
        ];

        return $rules;
    }
}

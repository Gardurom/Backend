<?php

namespace Tests\Unit\Persona;

use App\Persona\PersonFieldRules;
use App\Persona\PersonInputRules;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PersonFieldRulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::parse(
                '2026-10-19 12:00:00',
                'America/Mexico_City'
            )
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_exposes_reusable_person_field_rules(): void
    {
        $rules = PersonFieldRules::all();

        self::assertSame(
            [
                'curp',
                'rfc',
                'nombres',
                'apellido_paterno',
                'apellido_materno',
                'fecha_nacimiento',
                'sexo',
                'estado_civil',
                'correo_institucional',
                'correo_personal',
            ],
            array_keys($rules)
        );

        self::assertSame(
            [
                'nullable',
                'string',
                'size:18',
            ],
            $rules['curp']
        );

        self::assertSame(
            [
                'nullable',
                'string',
                'size:13',
            ],
            $rules['rfc']
        );

        self::assertSame(
            [
                'string',
                'max:150',
            ],
            $rules['nombres']
        );

        self::assertSame(
            [
                'nullable',
                'string',
                'max:254',
            ],
            $rules['correo_institucional']
        );

        self::assertSame(
            [
                'nullable',
                'string',
                'max:254',
            ],
            $rules['correo_personal']
        );

        self::assertContains(
            'before_or_equal:2026-10-19',
            $rules['fecha_nacimiento']
        );
    }

    public function test_api_create_keeps_current_explicit_whitelist(): void
    {
        $rules = PersonInputRules::forCreate();

        self::assertSame(
            [
                'nombres',
                'apellido_paterno',
                'apellido_materno',
                'sexo',
                'estado_civil',
                'fecha_nacimiento',
                'correo_personal',
            ],
            array_keys($rules)
        );

        self::assertArrayNotHasKey(
            'curp',
            $rules
        );

        self::assertArrayNotHasKey(
            'rfc',
            $rules
        );

        self::assertArrayNotHasKey(
            'correo_institucional',
            $rules
        );

        self::assertContains(
            'required',
            $rules['nombres']
        );

        self::assertContains(
            'required_without:apellido_materno',
            $rules['apellido_paterno']
        );

        self::assertContains(
            'required_without:apellido_paterno',
            $rules['apellido_materno']
        );
    }

    public function test_api_update_keeps_current_explicit_whitelist(): void
    {
        $rules = PersonInputRules::forUpdate();

        self::assertSame(
            [
                'nombres',
                'apellido_paterno',
                'apellido_materno',
                'sexo',
                'estado_civil',
                'fecha_nacimiento',
                'correo_personal',
            ],
            array_keys($rules)
        );

        self::assertArrayNotHasKey(
            'curp',
            $rules
        );

        self::assertArrayNotHasKey(
            'rfc',
            $rules
        );

        self::assertArrayNotHasKey(
            'correo_institucional',
            $rules
        );

        foreach ($rules as $fieldRules) {
            self::assertSame(
                'sometimes',
                $fieldRules[0]
            );
        }
    }
}

<?php

namespace Tests\Functional\Persona;

use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Functional\FunctionalTestCase;

class PersonDomainsTest extends FunctionalTestCase
{
    #[DataProvider('allowedValues')]
    public function test_acepta_valores_permitidos(
        string $field,
        ?string $value
    ): void {
        $attributes = [
            'nombres' => 'PRUEBA FUNCIONAL DOMINIOS',
            $field => $value,
        ];

        if ($field === 'estatus' && $value === 'BAJA') {
            $attributes['fecha_baja'] = $this->db->raw(
                'CURRENT_TIMESTAMP'
            );
        }

        $id = $this->db->table('institutional.persons')
            ->insertGetId($attributes, 'id_persona');

        $person = $this->db->table('institutional.persons')
            ->where('id_persona', $id)
            ->first();

        self::assertNotNull($person);

        self::assertSame(
            $value,
            $person->{$field},
            "El campo {$field} debe conservar el valor permitido."
        );
    }

    public static function allowedValues(): iterable
    {
        yield 'sexo MASCULINO' => ['sexo', 'MASCULINO'];
        yield 'sexo FEMENINO' => ['sexo', 'FEMENINO'];
        yield 'sexo nulo' => ['sexo', null];

        yield 'estado civil SOLTERO' => ['estado_civil', 'SOLTERO'];
        yield 'estado civil CASADO' => ['estado_civil', 'CASADO'];
        yield 'estado civil nulo' => ['estado_civil', null];

        yield 'estatus ACTIVO' => ['estatus', 'ACTIVO'];
        yield 'estatus INACTIVO' => ['estatus', 'INACTIVO'];
        yield 'estatus SUSPENDIDO' => ['estatus', 'SUSPENDIDO'];
        yield 'estatus BAJA con fecha' => ['estatus', 'BAJA'];

        yield 'fecha de nacimiento nula' => ['fecha_nacimiento', null];
    }

    #[DataProvider('invalidValues')]
    public function test_rechaza_valores_fuera_del_dominio(
        string $field,
        string $value,
        string $constraint
    ): void {
        $this->assertCheckRejected(
            function () use ($field, $value): void {
                $this->db->table('institutional.persons')->insert([
                    'nombres' => 'PRUEBA FUNCIONAL DOMINIOS',
                    $field => $value,
                ]);
            },
            $constraint
        );
    }

    public static function invalidValues(): iterable
    {
        yield 'sexo invalido' => [
            'sexo',
            'OTRO',
            'persons_sexo_check',
        ];

        yield 'estado civil invalido' => [
            'estado_civil',
            'OTRO',
            'persons_estado_civil_check',
        ];

        yield 'estatus invalido' => [
            'estatus',
            'PENDIENTE',
            'persons_estatus_check',
        ];
    }

    public function test_acepta_nacimiento_en_la_fecha_institucional_actual(): void
    {
        $person = $this->db->selectOne(
            "INSERT INTO institutional.persons (
                nombres, fecha_nacimiento
             )
             VALUES (
                ?,
                (
                    transaction_timestamp()
                    AT TIME ZONE 'America/Mexico_City'
                )::DATE
             )
             RETURNING id_persona,
                fecha_nacimiento = (
                    transaction_timestamp()
                    AT TIME ZONE 'America/Mexico_City'
                )::DATE AS fecha_correcta",
            ['PRUEBA FUNCIONAL FECHA ACTUAL']
        );

        self::assertNotNull($person);
        self::assertTrue($person->fecha_correcta);
    }

    public function test_rechaza_nacimiento_posterior_a_la_fecha_institucional(): void
    {
        $this->assertCheckRejected(
            function (): void {
                $this->db->insert(
                    "INSERT INTO institutional.persons (
                        nombres, fecha_nacimiento
                     )
                     VALUES (
                        ?,
                        (
                            transaction_timestamp()
                            AT TIME ZONE 'America/Mexico_City'
                        )::DATE + 1
                     )",
                    ['PRUEBA FUNCIONAL FECHA FUTURA']
                );
            },
            'persons_fecha_nacimiento_check'
        );
    }

    private function assertCheckRejected(
        callable $operation,
        string $constraint
    ): void {
        try {
            $operation();
        } catch (QueryException $error) {
            self::assertSame(
                '23514',
                $error->getCode(),
                'El rechazo debe proceder de una restriccion CHECK.'
            );

            self::assertStringContainsString(
                $constraint,
                $error->getMessage(),
                'Debe intervenir la restriccion esperada.'
            );

            return;
        }

        self::fail(
            "La operacion debio ser rechazada por {$constraint}."
        );
    }
}
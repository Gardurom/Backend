<?php

namespace Tests\Functional\Persona;

use Illuminate\Database\QueryException;
use Tests\Functional\FunctionalTestCase;

class PersonRegistrationTest extends FunctionalTestCase
{
    public function test_registration_generates_identity_and_initial_values(): void
    {
        $person = $this->registerPerson();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $person->id_persona,
            'El identificador debe ser un UUID version 7.'
        );

        self::assertMatchesRegularExpression(
            '/^[0-9]{4}-[0-9]{8}$/',
            $person->num_expediente,
            'El expediente debe tener el formato YYYY-NNNNNNNN.'
        );

        self::assertGreaterThan(
            0,
            (int) substr($person->num_expediente, 5),
            'El consecutivo del expediente debe ser positivo.'
        );

        self::assertSame('ACTIVO', $person->estatus);
        self::assertNull($person->fecha_baja);

        foreach (['fecha_alta', 'created_at', 'updated_at'] as $field) {
            self::assertNotNull(
                $person->{$field},
                "El campo {$field} debe generarse al registrar Persona."
            );
        }

        self::assertSame(
            1,
            $this->db->table('institutional.persons')
                ->where('id_persona', $person->id_persona)
                ->count(),
            'La Persona debe existir dentro de la transaccion.'
        );
    }

    public function test_two_registrations_receive_consecutive_expedientes(): void
    {
        $first = $this->registerPerson();
        $second = $this->registerPerson();

        self::assertNotSame(
            $first->id_persona,
            $second->id_persona,
            'Cada Persona debe recibir un identificador distinto.'
        );

        self::assertSame(
            substr($first->num_expediente, 0, 4),
            substr($second->num_expediente, 0, 4),
            'Las asignaciones de esta transaccion deben usar el mismo año.'
        );

        self::assertSame(
            (int) substr($first->num_expediente, 5) + 1,
            (int) substr($second->num_expediente, 5),
            'El segundo expediente debe incrementar el consecutivo en uno.'
        );
    }

    public function test_registration_rejects_an_empty_name(): void
    {
        try {
            $this->db->insert(
                'INSERT INTO institutional.persons (nombres) VALUES (?)',
                ['']
            );
        } catch (QueryException $error) {
            self::assertSame(
                '23514',
                $error->getCode(),
                'El rechazo debe proceder de una restriccion CHECK.'
            );

            self::assertStringContainsString(
                'persons_nombres_check',
                $error->getMessage(),
                'Debe intervenir la restriccion del nombre.'
            );

            return;
        }

        self::fail('PostgreSQL permitio registrar una Persona con nombre vacio.');
    }

    private function registerPerson(): object
    {
        return $this->db->selectOne(
            'INSERT INTO institutional.persons (nombres)
             VALUES (?)
             RETURNING id_persona, num_expediente, estatus,
                       fecha_alta, fecha_baja, created_at, updated_at',
            ['PRUEBA FUNCIONAL PERSONA']
        );
    }
}
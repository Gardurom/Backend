<?php

namespace Tests\Functional\Persona;

use Illuminate\Database\QueryException;
use Tests\Functional\FunctionalTestCase;

class PersonTransactionTest extends FunctionalTestCase
{
    public function test_rollback_revierte_persona_y_expediente(): void
    {
        $initialCount = $this->db
            ->table('institutional.persons')
            ->count();

        $this->db->beginTransaction();

        try {
            $temporary = $this->registerPerson();

            self::assertSame(
                $initialCount + 1,
                $this->db->table('institutional.persons')->count()
            );
        } finally {
            $this->db->rollBack();
        }

        self::assertSame(
            $initialCount,
            $this->db->table('institutional.persons')->count(),
            'El rollback debe restaurar la cantidad inicial de Personas.'
        );

        self::assertFalse(
            $this->db->table('institutional.persons')
                ->where('id_persona', $temporary->id_persona)
                ->exists(),
            'La Persona temporal debe desaparecer tras el rollback.'
        );

        $next = $this->registerPerson();

        self::assertSame(
            $temporary->num_expediente,
            $next->num_expediente,
            'El expediente revertido debe quedar disponible.'
        );
    }

    public function test_registro_rechazado_no_consume_expediente(): void
    {
        $this->db->beginTransaction();

        try {
            $expected = $this->registerPerson()->num_expediente;
        } finally {
            $this->db->rollBack();
        }

        $rejected = false;

        $this->db->beginTransaction();

        try {
            $this->db->insert(
                'INSERT INTO institutional.persons (nombres) VALUES (?)',
                ['']
            );
        } catch (QueryException $error) {
            self::assertSame('23514', $error->getCode());

            self::assertStringContainsString(
                'persons_nombres_check',
                $error->getMessage()
            );

            $rejected = true;
        } finally {
            $this->db->rollBack();
        }

        self::assertTrue(
            $rejected,
            'El registro con nombre vacio debe ser rechazado.'
        );

        $person = $this->registerPerson();

        self::assertSame(
            $expected,
            $person->num_expediente,
            'El registro rechazado no debe consumir el expediente.'
        );
    }

    private function registerPerson(): object
    {
        return $this->db->selectOne(
            'INSERT INTO institutional.persons (nombres)
             VALUES (?)
             RETURNING id_persona, num_expediente',
            ['PRUEBA FUNCIONAL TRANSACCION']
        );
    }
}
<?php

namespace Tests\Functional\Persona;

use DateTimeImmutable;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Functional\FunctionalTestCase;

class PersonLifecycleTest extends FunctionalTestCase
{
    public function test_baja_y_reingreso_conservan_identidad_expediente_y_alta(): void
    {
        $original = $this->registerPerson();

        $baja = $this->db->selectOne(
            "UPDATE institutional.persons
             SET estatus = 'BAJA',
                 fecha_baja = fecha_alta,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id_persona = ?
             RETURNING id_persona, num_expediente, fecha_alta,
                       fecha_baja, estatus",
            [$original->id_persona]
        );

        self::assertSame('BAJA', $baja->estatus);
        self::assertNotNull($baja->fecha_baja);
        self::assertSame($original->id_persona, $baja->id_persona);
        self::assertSame($original->num_expediente, $baja->num_expediente);
        self::assertSame($original->fecha_alta, $baja->fecha_alta);

        $reingreso = $this->db->selectOne(
            "UPDATE institutional.persons
             SET estatus = 'ACTIVO',
                 fecha_baja = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id_persona = ?
             RETURNING id_persona, num_expediente, fecha_alta,
                       fecha_baja, estatus",
            [$original->id_persona]
        );

        self::assertSame('ACTIVO', $reingreso->estatus);
        self::assertNull($reingreso->fecha_baja);
        self::assertSame($original->id_persona, $reingreso->id_persona);
        self::assertSame($original->num_expediente, $reingreso->num_expediente);
        self::assertSame($original->fecha_alta, $reingreso->fecha_alta);

        self::assertSame(
            1,
            $this->db->table('institutional.persons')
                ->where('id_persona', $original->id_persona)
                ->count()
        );
    }

    #[DataProvider('inconsistentStatuses')]
    public function test_rechaza_incoherencia_entre_estatus_y_fecha_baja(
        string $status,
        bool $hasDate
    ): void {
        $person = $this->registerPerson();

        $this->assertCheckRejected(
            function () use ($person, $status, $hasDate): void {
                $this->db->table('institutional.persons')
                    ->where('id_persona', $person->id_persona)
                    ->update([
                        'estatus' => $status,
                        'fecha_baja' => $hasDate ? $person->fecha_alta : null,
                    ]);
            },
            'persons_baja_estatus_check'
        );
    }

    public static function inconsistentStatuses(): iterable
    {
        yield 'BAJA sin fecha' => ['BAJA', false];
        yield 'ACTIVO con fecha de baja' => ['ACTIVO', true];
        yield 'INACTIVO con fecha de baja' => ['INACTIVO', true];
        yield 'SUSPENDIDO con fecha de baja' => ['SUSPENDIDO', true];
    }

    public function test_rechaza_fecha_baja_anterior_al_alta(): void
    {
        $person = $this->registerPerson();

        $earlierDate = (new DateTimeImmutable($person->fecha_alta))
            ->modify('-1 second')
            ->format('Y-m-d H:i:sP');

        $this->assertCheckRejected(
            function () use ($person, $earlierDate): void {
                $this->db->table('institutional.persons')
                    ->where('id_persona', $person->id_persona)
                    ->update([
                        'estatus' => 'BAJA',
                        'fecha_baja' => $earlierDate,
                    ]);
            },
            'persons_fecha_baja_check'
        );
    }

    private function registerPerson(): object
    {
        return $this->db->selectOne(
            'INSERT INTO institutional.persons (nombres)
             VALUES (?)
             RETURNING id_persona, num_expediente, fecha_alta',
            ['PRUEBA FUNCIONAL CICLO PERSONA']
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
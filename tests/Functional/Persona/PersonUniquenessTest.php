<?php

namespace Tests\Functional\Persona;

use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Functional\FunctionalTestCase;

class PersonUniquenessTest extends FunctionalTestCase
{
    #[DataProvider('uniqueFields')]
    public function test_rechaza_identificadores_y_correo_institucional_duplicados(
        string $field,
        string $constraint
    ): void {
        $value = match ($field) {
            'curp' => strtoupper(bin2hex(random_bytes(9))),
            'rfc' => strtoupper(substr(bin2hex(random_bytes(7)), 0, 13)),
            'correo_institucional' =>
                'prueba.' . bin2hex(random_bytes(8)) . '@example.invalid',
        };

        $this->registerPerson([$field => $value]);

        try {
            $this->registerPerson([$field => $value]);
        } catch (QueryException $error) {
            self::assertSame(
                '23505',
                $error->getCode(),
                'El rechazo debe proceder de una restriccion de unicidad.'
            );

            self::assertStringContainsString(
                $constraint,
                $error->getMessage(),
                'Debe intervenir la restriccion esperada.'
            );

            return;
        }

        self::fail("PostgreSQL permitio duplicar el campo {$field}.");
    }

    public static function uniqueFields(): iterable
    {
        yield 'CURP' => [
            'curp',
            'institutional_persons_curp_unique',
        ];

        yield 'RFC' => [
            'rfc',
            'institutional_persons_rfc_unique',
        ];

        yield 'correo institucional' => [
            'correo_institucional',
            'persons_correo_institucional_unique',
        ];
    }

    public function test_permite_varias_personas_con_identificadores_nulos(): void
    {
        $attributes = [
            'curp' => null,
            'rfc' => null,
            'correo_institucional' => null,
        ];

        $firstId = $this->registerPerson($attributes);
        $secondId = $this->registerPerson($attributes);

        self::assertNotSame($firstId, $secondId);

        $persons = $this->db->table('institutional.persons')
            ->whereIn('id_persona', [$firstId, $secondId])
            ->get(['curp', 'rfc', 'correo_institucional']);

        self::assertCount(2, $persons);

        foreach ($persons as $person) {
            self::assertNull($person->curp);
            self::assertNull($person->rfc);
            self::assertNull($person->correo_institucional);
        }
    }

    public function test_permite_compartir_correo_personal(): void
    {
        $email = 'compartido.'
            . bin2hex(random_bytes(8))
            . '@example.invalid';

        $firstId = $this->registerPerson([
            'correo_personal' => $email,
        ]);

        $secondId = $this->registerPerson([
            'correo_personal' => $email,
        ]);

        self::assertNotSame($firstId, $secondId);

        self::assertSame(
            2,
            $this->db->table('institutional.persons')
                ->whereIn('id_persona', [$firstId, $secondId])
                ->where('correo_personal', $email)
                ->count(),
            'Dos Personas deben poder compartir el correo personal.'
        );
    }

    private function registerPerson(array $attributes = []): string
    {
        return $this->db->table('institutional.persons')->insertGetId(
            array_merge(
                ['nombres' => 'PRUEBA FUNCIONAL UNICIDAD'],
                $attributes
            ),
            'id_persona'
        );
    }
}
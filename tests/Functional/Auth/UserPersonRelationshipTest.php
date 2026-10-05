<?php

namespace Tests\Functional\Auth;

use App\Models\Person;
use App\Models\User;
use Illuminate\Database\QueryException;
use Tests\Functional\HttpFunctionalTestCase;

class UserPersonRelationshipTest extends HttpFunctionalTestCase
{
    public function test_user_belongs_to_person(): void
    {
        $personId = (string) $this->db
            ->table('institutional.persons')
            ->insertGetId(
                [
                    'nombres' => 'PERSONA USUARIO SIGA',
                ],
                'id_persona'
            );

        $user = User::factory()->create([
            'name' => 'USUARIO VINCULADO SIGA',
            'email' => 'user.person.relationship@siga.test',
            'id_persona' => $personId,
        ]);

        self::assertNotNull($user->person);
        self::assertSame(
            $personId,
            (string) $user->person->id_persona
        );
    }

    public function test_person_has_one_user(): void
    {
        $personId = (string) $this->db
            ->table('institutional.persons')
            ->insertGetId(
                [
                    'nombres' => 'PERSONA CON CUENTA SIGA',
                ],
                'id_persona'
            );

        $user = User::factory()->create([
            'name' => 'CUENTA PERSONA SIGA',
            'email' => 'person.user.relationship@siga.test',
            'id_persona' => $personId,
        ]);

        $person = Person::query()->findOrFail($personId);

        self::assertNotNull($person->user);
        self::assertSame(
            $user->id,
            $person->user->id
        );
    }

    public function test_user_cannot_reference_missing_person(): void
    {
        try {
            User::factory()->create([
                'name' => 'USUARIO PERSONA INEXISTENTE',
                'email' => 'user.missing.person@siga.test',
                'id_persona' => '01900000-0000-7000-8000-000000000001',
            ]);
        } catch (QueryException $error) {
            self::assertSame(
                '23503',
                $error->getCode(),
                'Debe rechazarse un usuario vinculado a una Persona inexistente.'
            );

            return;
        }

        self::fail(
            'La base debió rechazar la referencia a una Persona inexistente.'
        );
    }

    public function test_person_cannot_be_linked_to_two_users(): void
    {
        $personId = (string) $this->db
            ->table('institutional.persons')
            ->insertGetId(
                [
                    'nombres' => 'PERSONA USUARIO UNICO SIGA',
                ],
                'id_persona'
            );

        User::factory()->create([
            'name' => 'PRIMER USUARIO PERSONA',
            'email' => 'first.person.user@siga.test',
            'id_persona' => $personId,
        ]);

        try {
            User::factory()->create([
                'name' => 'SEGUNDO USUARIO PERSONA',
                'email' => 'second.person.user@siga.test',
                'id_persona' => $personId,
            ]);
        } catch (QueryException $error) {
            self::assertSame(
                '23505',
                $error->getCode(),
                'Una Persona no debe estar vinculada a dos usuarios.'
            );

            return;
        }

        self::fail(
            'La base debió impedir una segunda cuenta para la misma Persona.'
        );
    }
}

<?php

namespace App\Actions\Persona;

use App\Models\Person;

class FindPerson
{
    public function execute(string $personId): Person
    {
        return Person::query()->findOrFail($personId);
    }
}

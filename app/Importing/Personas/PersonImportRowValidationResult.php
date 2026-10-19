<?php

namespace App\Importing\Personas;

final readonly class PersonImportRowValidationResult
{
    /**
     * @param  array<string, mixed>  $normalizedData
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        public array $normalizedData,
        public array $errors
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}

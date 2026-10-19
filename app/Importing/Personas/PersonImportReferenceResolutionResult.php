<?php

namespace App\Importing\Personas;

final readonly class PersonImportReferenceResolutionResult
{
    /**
     * @param  array{
     *     id_pais_origen: ?string,
     *     id_pais_nacimiento: ?string,
     *     id_territorio_nacimiento: ?string
     * }  $resolvedData
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        public array $resolvedData,
        public array $errors
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}

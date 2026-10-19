<?php

namespace App\Importing\Personas;

final class PersonImportRowValidationPipeline
{
    public function __construct(
        private readonly PersonImportRowValidator $rowValidator,
        private readonly PersonImportReferenceResolver $referenceResolver
    ) {}

    /**
     * @param  array<string, mixed>  $rawData
     */
    public function validate(
        array $rawData
    ): PersonImportRowValidationResult {
        $localResult =
            $this->rowValidator->validate(
                $rawData
            );

        $normalizedData = array_merge(
            $localResult->normalizedData,
            $this->emptyResolvedData()
        );

        if (! $localResult->isValid()) {
            return new PersonImportRowValidationResult(
                normalizedData: $normalizedData,
                errors: $localResult->errors
            );
        }

        $referenceResult =
            $this->referenceResolver->resolve(
                $localResult->normalizedData
            );

        $normalizedData = array_merge(
            $localResult->normalizedData,
            $referenceResult->resolvedData
        );

        return new PersonImportRowValidationResult(
            normalizedData: $normalizedData,
            errors: $referenceResult->errors
        );
    }

    /**
     * @return array{
     *     id_pais_origen: null,
     *     id_pais_nacimiento: null,
     *     id_territorio_nacimiento: null
     * }
     */
    private function emptyResolvedData(): array
    {
        return [
            'id_pais_origen' => null,
            'id_pais_nacimiento' => null,
            'id_territorio_nacimiento' => null,
        ];
    }
}

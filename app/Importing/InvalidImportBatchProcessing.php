<?php

namespace App\Importing;

use DomainException;

final class InvalidImportBatchProcessing extends DomainException
{
    public function __construct(
        string $message = 'El lote no puede ser procesado.'
    ) {
        parent::__construct(
            $message
        );
    }
}

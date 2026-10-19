<?php

namespace App\Importing;

use DomainException;

class InvalidImportBatchValidation extends DomainException
{
    public function __construct(
        string $message = 'El lote no puede iniciar validacion.'
    ) {
        parent::__construct($message);
    }
}

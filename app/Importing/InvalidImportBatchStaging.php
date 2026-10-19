<?php

namespace App\Importing;

use DomainException;

class InvalidImportBatchStaging extends DomainException
{
    public function __construct(
        string $message = 'El lote no puede entrar a staging.'
    ) {
        parent::__construct($message);
    }
}

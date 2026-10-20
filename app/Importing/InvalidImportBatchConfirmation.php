<?php

namespace App\Importing;

use DomainException;

final class InvalidImportBatchConfirmation extends DomainException
{
    public function __construct(
        string $message = 'El lote no puede ser confirmado.'
    ) {
        parent::__construct(
            $message
        );
    }
}

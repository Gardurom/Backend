<?php

namespace App\Importing;

use DomainException;

class InvalidImportBatchTransition extends DomainException
{
    public function __construct(
        ImportBatchStatus $from,
        ImportBatchStatus $to
    ) {
        parent::__construct(
            "Transicion de lote de importacion no permitida: {$from->value} -> {$to->value}."
        );
    }
}

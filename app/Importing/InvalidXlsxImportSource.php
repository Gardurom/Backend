<?php

namespace App\Importing;

use DomainException;
use Throwable;

class InvalidXlsxImportSource extends DomainException
{
    public function __construct(
        string $message = 'El archivo XLSX de importacion no es valido.',
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            0,
            $previous
        );
    }
}

<?php

namespace App\Importing;

enum ImportRowStatus: string
{
    case PENDING = 'PENDING';
    case VALID = 'VALID';
    case INVALID = 'INVALID';
    case PROCESSED = 'PROCESSED';
    case FAILED = 'FAILED';
    case SKIPPED = 'SKIPPED';
}

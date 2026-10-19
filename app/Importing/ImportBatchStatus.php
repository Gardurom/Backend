<?php

namespace App\Importing;

enum ImportBatchStatus: string
{
    case UPLOADED = 'UPLOADED';
    case VALIDATING = 'VALIDATING';
    case READY = 'READY';
    case QUEUED = 'QUEUED';
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
}

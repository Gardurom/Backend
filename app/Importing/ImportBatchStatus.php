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

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::UPLOADED => in_array(
                $target,
                [
                    self::VALIDATING,
                    self::CANCELLED,
                ],
                true
            ),

            self::VALIDATING => in_array(
                $target,
                [
                    self::READY,
                    self::FAILED,
                    self::CANCELLED,
                ],
                true
            ),

            self::READY => in_array(
                $target,
                [
                    self::QUEUED,
                    self::CANCELLED,
                ],
                true
            ),

            self::QUEUED => in_array(
                $target,
                [
                    self::PROCESSING,
                    self::FAILED,
                    self::CANCELLED,
                ],
                true
            ),

            self::PROCESSING => in_array(
                $target,
                [
                    self::COMPLETED,
                    self::FAILED,
                ],
                true
            ),

            self::COMPLETED,
            self::FAILED,
            self::CANCELLED => false,
        };
    }
}

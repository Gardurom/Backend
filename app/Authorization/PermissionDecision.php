<?php

namespace App\Authorization;

enum PermissionDecision: string
{
    case ALLOW = 'ALLOW';
    case DENY = 'DENY';
    case NO_RULE = 'NO_RULE';

    public function allows(): bool
    {
        return $this === self::ALLOW;
    }
}

<?php

namespace App\Exceptions;

use RuntimeException;

class StaffScheduleConflictException extends RuntimeException
{
    /**
     * @param  array<int, array{id: int, number: string, starts_at: string}>  $conflicts
     */
    public function __construct(public readonly array $conflicts)
    {
        parent::__construct('The staff change conflicts with confirmed appointments.');
    }
}

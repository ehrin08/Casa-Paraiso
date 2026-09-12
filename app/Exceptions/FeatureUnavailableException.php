<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FeatureUnavailableException extends HttpException implements ShouldntReport
{
    private function __construct(public readonly string $featureName)
    {
        parent::__construct(503, 'This feature is not available yet.');
    }

    public static function for(string $featureName): self
    {
        return new self($featureName);
    }
}

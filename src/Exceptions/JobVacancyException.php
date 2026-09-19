<?php

namespace Nawasara\JobVacancy\Exceptions;

use RuntimeException;

/**
 * Base exception for the job-vacancy domain.
 */
class JobVacancyException extends RuntimeException
{
    public static function upstreamUnavailable(string $message = 'The job vacancy service is unreachable.', ?\Throwable $previous = null): static
    {
        return new static($message, previous: $previous);
    }
}
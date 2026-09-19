<?php

namespace Nawasara\JobVacancy\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Thrown when the upstream service responds with an HTTP error.
 * The controller error-map converts these into the standard Nawasara format.
 */
class UpstreamException extends RuntimeException
{
    private string $errorCode;

    public function __construct(string $message, string $errorCode, ?\Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public static function fromResponse(Response $response): static
    {
        $status = $response->status();

        if (in_array($status, [401, 403], true)) {
            return new static(
                'The upstream API token is invalid — check credentials in Vault.',
                'upstream_auth_failed',
            );
        }

        if ($status === 404) {
            return new static(
                'The upstream resource was not found.',
                'upstream_not_found',
            );
        }

        return new static(
            'The upstream service is having issues (HTTP '.$status.').',
            'upstream_error',
        );
    }
}
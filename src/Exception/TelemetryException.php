<?php

declare(strict_types=1);

namespace Neika\Sdk\Exception;

class TelemetryException extends \RuntimeException
{
    public function __construct(
        string                   $message,
        private readonly int     $statusCode,
        private readonly ?string $backendErrorCode = null,
        ?\Throwable              $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBackendErrorCode(): ?string
    {
        return $this->backendErrorCode;
    }
}

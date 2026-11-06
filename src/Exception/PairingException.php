<?php

declare(strict_types=1);

namespace Neika\Sdk\Exception;

class PairingException extends \RuntimeException
{
    public const TRANSPORT_FAILED = 'transport_failed';

    public const INVALID_RESPONSE = 'invalid_response';

    public function __construct(
        string                  $message,
        private readonly string $errorCode,
        ?\Throwable             $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}

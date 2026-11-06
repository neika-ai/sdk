<?php

declare(strict_types=1);

namespace Neika\Sdk\Exception;

use Neika\Sdk\Enum\ApiErrorCode;

abstract class ChatbotApiException extends \RuntimeException
{
    abstract public function getErrorCode(): ApiErrorCode;

    abstract public function getStatusCode(): int;

    public function getViolations(): array
    {
        return [];
    }
}

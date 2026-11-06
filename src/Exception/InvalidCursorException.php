<?php

declare(strict_types=1);

namespace Neika\Sdk\Exception;

use Neika\Sdk\Enum\ApiErrorCode;

class InvalidCursorException extends ChatbotApiException
{
    public function __construct(string $cursor)
    {
        parent::__construct(sprintf('Cursor "%s" cannot be decoded.', $cursor));
    }

    public function getErrorCode(): ApiErrorCode
    {
        return ApiErrorCode::InvalidCursor;
    }

    public function getStatusCode(): int
    {
        return 400;
    }
}

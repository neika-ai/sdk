<?php

declare(strict_types=1);

namespace Neika\Sdk\Exception;

use Neika\Sdk\Enum\ApiErrorCode;

class SourceNotFoundException extends ChatbotApiException
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf('Data source "%s" is not registered.', $name));
    }

    public function getErrorCode(): ApiErrorCode
    {
        return ApiErrorCode::SourceNotFound;
    }

    public function getStatusCode(): int
    {
        return 404;
    }
}

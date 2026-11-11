<?php

declare(strict_types=1);

namespace Neika\Sdk\Exception;

use Neika\Sdk\Enum\ApiErrorCode;

class IdentityRequiredException extends ChatbotApiException
{
    public function __construct()
    {
        parent::__construct('This tool call carries no identity.');
    }

    public function getErrorCode(): ApiErrorCode
    {
        return ApiErrorCode::IdentityRequired;
    }

    public function getStatusCode(): int
    {
        return 403;
    }
}

<?php

declare(strict_types=1);

namespace Neika\Sdk\Exception;

use Neika\Sdk\Enum\ApiErrorCode;

class ArgumentsValidationException extends ChatbotApiException
{
    public function __construct(
        private readonly array $violations,
    ) {
        parent::__construct('Tool arguments are invalid.');
    }

    public function getErrorCode(): ApiErrorCode
    {
        return ApiErrorCode::ValidationFailed;
    }

    public function getStatusCode(): int
    {
        return 422;
    }

    public function getViolations(): array
    {
        return $this->violations;
    }
}

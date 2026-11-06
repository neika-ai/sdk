<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ToolCallRequest
{
    public function __construct(
        public readonly array $arguments,
        #[Assert\Valid]
        public readonly ToolCallContext $context,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ToolCallContext
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public readonly string $conversationId,
        #[Assert\NotBlank]
        #[Assert\Locale]
        public readonly string $locale,
        public readonly ?string $channelCode = null,
    ) {
    }

    public function withLocale(string $locale): self
    {
        return new self($this->conversationId, $locale, $this->channelCode);
    }
}

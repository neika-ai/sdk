<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

class ContentItem implements \JsonSerializable
{
    public function __construct(
        public readonly string $text,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => 'text',
            'text' => $this->text,
        ];
    }
}

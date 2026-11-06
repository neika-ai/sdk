<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

class ToolResult implements \JsonSerializable
{
    public function __construct(
        public readonly array $content,
        public readonly array $blocks = [],
        public readonly bool $isError = false,
        public readonly array $privateValues = [],
    ) {
    }

    public function jsonSerialize(): array
    {
        $serialized = [
            'content' => array_map(
                fn (ContentItem $item): array => $item->jsonSerialize(),
                $this->content,
            ),
            'blocks' => array_map(
                fn (\JsonSerializable $block): array => $block->jsonSerialize(),
                $this->blocks,
            ),
            'isError' => $this->isError,
        ];

        if ($this->privateValues !== []) {
            $serialized['privateValues'] = array_map(
                fn (PrivateValue $privateValue): array => $privateValue->jsonSerialize(),
                $this->privateValues,
            );
        }

        return $serialized;
    }
}

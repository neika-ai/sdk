<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

use Neika\Sdk\Enum\DocumentKind;

class SourceDocument implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly string $title,
        public readonly string $text,
        public readonly DocumentKind $kind,
        public readonly array $metadata,
        public readonly string $updatedAt,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'title' => $this->title,
            'text' => $this->text,
            'kind' => $this->kind->value,
            'metadata' => $this->metadata,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

class DocumentPage implements \JsonSerializable
{
    public function __construct(
        public readonly array $documents,
        public readonly ?string $nextCursor = null,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'documents' => array_map(
                fn (SourceDocument $document): array => $document->jsonSerialize(),
                $this->documents,
            ),
            'nextCursor' => $this->nextCursor,
        ];
    }
}

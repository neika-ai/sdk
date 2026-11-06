<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

class ChoiceItem implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
        ];
    }
}

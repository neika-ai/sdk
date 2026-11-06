<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

class PrivateValue implements \JsonSerializable
{
    public function __construct(
        public readonly string $key,
        public readonly string $value,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
        ];
    }
}

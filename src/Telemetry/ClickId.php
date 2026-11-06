<?php

declare(strict_types=1);

namespace Neika\Sdk\Telemetry;

class ClickId
{
    public function getQueryParameterName(): string
    {
        return 'neikaclid';
    }

    public function getMaxLength(): int
    {
        return 64;
    }

    /**
     * @param array<array-key, mixed> $query
     */
    public function find(array $query): ?string
    {
        $value = $query[$this->getQueryParameterName()] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $length = strlen($value);
        $isWithinRange = $length >= 1 && $length <= $this->getMaxLength();
        if (!$isWithinRange) {
            return null;
        }

        return $value;
    }
}

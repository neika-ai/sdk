<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

class ChatOrder implements \JsonSerializable
{
    public function __construct(
        public readonly string $clickId,
        public readonly string $orderNumber,
        public readonly int    $revenue,
        public readonly string $currency,
    ) {
    }

    /**
     * @return array{clickId: string, orderNumber: string, revenue: int, currency: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'clickId' => $this->clickId,
            'orderNumber' => $this->orderNumber,
            'revenue' => $this->revenue,
            'currency' => $this->currency,
        ];
    }
}

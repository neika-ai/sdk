<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

class CatalogChangeResult
{
    /**
     * @param list<CatalogChangeJob> $jobs
     */
    public function __construct(
        public readonly bool  $accepted,
        public readonly array $jobs = [],
        public readonly ?int  $retryAfterSeconds = null,
    ) {
    }

    public function isThrottled(): bool
    {
        return $this->retryAfterSeconds !== null;
    }
}

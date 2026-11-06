<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

class SiteCredentials
{
    public function __construct(
        public readonly string $siteKey,

        #[\SensitiveParameter]
        public readonly string $ingestSecret,
    ) {
    }

    public function hasIngestSecret(): bool
    {
        return $this->ingestSecret !== '';
    }

    public function getBearerToken(): string
    {
        return $this->siteKey . '.' . $this->ingestSecret;
    }
}

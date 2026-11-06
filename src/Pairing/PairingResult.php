<?php

declare(strict_types=1);

namespace Neika\Sdk\Pairing;

class PairingResult
{
    /**
     * @param list<string> $channelCodes
     * @param list<string> $verifiedDomains
     */
    public function __construct(
        public readonly string $siteKey,
        public readonly string $baseUrl,
        public readonly array  $channelCodes,
        public readonly string $returnUrl,
        public readonly array  $verifiedDomains,
    ) {
    }
}

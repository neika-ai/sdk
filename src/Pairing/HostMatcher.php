<?php

declare(strict_types=1);

namespace Neika\Sdk\Pairing;

class HostMatcher
{
    public function normalize(string $hostOrUrl): ?string
    {
        $trimmed = trim($hostOrUrl);
        $hasScheme = str_contains($trimmed, '://');
        $url = $hasScheme ? $trimmed : '//' . $trimmed;
        $host = parse_url($url, PHP_URL_HOST);

        if (!is_string($host)) {
            return null;
        }

        $hostWithoutTrailingDot = rtrim($host, '.');

        if ($hostWithoutTrailingDot === '') {
            return null;
        }

        $asciiHost = idn_to_ascii($hostWithoutTrailingDot, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

        if ($asciiHost === false) {
            return null;
        }

        return strtolower($asciiHost);
    }

    public function isSameHost(string $hostOrUrl, string $otherHostOrUrl): bool
    {
        $host = $this->normalize($hostOrUrl);
        $otherHost = $this->normalize($otherHostOrUrl);

        if ($host === null) {
            return false;
        }

        return $host === $otherHost;
    }

    public function isCoveredByDomain(string $hostOrUrl, string $domain): bool
    {
        $host = $this->normalizeWithoutWww($hostOrUrl);
        $normalizedDomain = $this->normalizeWithoutWww($domain);

        if ($host === null) {
            return false;
        }

        if ($normalizedDomain === null) {
            return false;
        }

        $isSameHost = $host === $normalizedDomain;
        $isSubdomain = str_ends_with($host, '.' . $normalizedDomain);

        return $isSameHost || $isSubdomain;
    }

    private function normalizeWithoutWww(string $hostOrUrl): ?string
    {
        $host = $this->normalize($hostOrUrl);

        if ($host === null) {
            return null;
        }

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}

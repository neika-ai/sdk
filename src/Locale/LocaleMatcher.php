<?php

declare(strict_types=1);

namespace Neika\Sdk\Locale;

use Neika\Sdk\Exception\InvalidLocaleException;
use Symfony\Component\Intl\Locales;

class LocaleMatcher
{
    /**
     * @param list<string> $servedLocales
     *
     * @throws InvalidLocaleException
     */
    public function resolveServedLocaleOrFail(string $requested, array $servedLocales): string
    {
        $servedLocale = $this->resolveServedLocale($requested, $servedLocales);

        if ($servedLocale === null) {
            throw new InvalidLocaleException($requested);
        }

        return $servedLocale;
    }

    /**
     * @param list<string> $servedLocales
     */
    public function resolveServedLocale(string $requested, array $servedLocales): ?string
    {
        $requestedLocale = $this->getKnownLocale($requested);

        if ($requestedLocale === null) {
            return null;
        }

        $sameLocale = $this->findSameLocale($requestedLocale, $servedLocales);

        if ($sameLocale !== null) {
            return $sameLocale;
        }

        return $this->findSameLanguage($requestedLocale, $servedLocales);
    }

    /**
     * @param list<string> $servedLocales
     */
    private function findSameLocale(string $requestedLocale, array $servedLocales): ?string
    {
        foreach ($servedLocales as $servedLocale) {
            $isSameLocale = $this->getCanonicalLocale($servedLocale) === $requestedLocale;

            if ($isSameLocale) {
                return $servedLocale;
            }
        }

        return null;
    }

    /**
     * @param list<string> $servedLocales
     */
    private function findSameLanguage(string $requestedLocale, array $servedLocales): ?string
    {
        $requestedLanguage = $this->getLanguage($requestedLocale);

        foreach ($servedLocales as $servedLocale) {
            $isSameLanguage = $this->getLanguage($servedLocale) === $requestedLanguage;

            if ($isSameLanguage) {
                return $servedLocale;
            }
        }

        return null;
    }

    private function getKnownLocale(string $locale): ?string
    {
        $canonicalLocale = $this->getCanonicalLocale($locale);
        $isKnown = Locales::exists($canonicalLocale);

        if (!$isKnown) {
            return null;
        }

        return $canonicalLocale;
    }

    private function getLanguage(string $locale): string
    {
        $canonicalLocale = $this->getCanonicalLocale($locale);

        if ($canonicalLocale === '') {
            return '';
        }

        return (string) \Locale::getPrimaryLanguage($canonicalLocale);
    }

    private function getCanonicalLocale(string $locale): string
    {
        if ($locale === '') {
            return '';
        }

        return (string) \Locale::canonicalize($locale);
    }
}

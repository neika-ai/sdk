<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\Locale;

use Neika\Sdk\Exception\InvalidLocaleException;
use Neika\Sdk\Locale\LocaleMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LocaleMatcherTest extends TestCase
{
    #[DataProvider('provideRequestedLocales')]
    public function testServedLocaleKeepsTheServedSpelling(string $requested, ?string $expected): void
    {
        $matcher = new LocaleMatcher();

        self::assertSame($expected, $matcher->resolveServedLocale($requested, ['cs_CZ', 'en_US']));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function provideRequestedLocales(): iterable
    {
        yield 'language only' => ['cs', 'cs_CZ'];
        yield 'exact' => ['cs_CZ', 'cs_CZ'];
        yield 'hyphen separated' => ['en-GB', 'en_US'];
        yield 'lower cased region' => ['cs_cz', 'cs_CZ'];
        yield 'upper cased language' => ['CS-cz', 'cs_CZ'];
        yield 'second locale' => ['en', 'en_US'];
        yield 'unserved language' => ['de', null];
        yield 'unknown region' => ['cs_ZZ', null];
        yield 'unknown language' => ['zz_ZZ', null];
        yield 'empty' => ['', null];
        yield 'accept language list' => ['cs-CZ,cs;q=0.9', null];
    }

    public function testExactSpellingWinsOverALaterLanguageMatch(): void
    {
        $matcher = new LocaleMatcher();

        self::assertSame('de_AT', $matcher->resolveServedLocale('de_AT', ['de_DE', 'de_AT']));
    }

    public function testFailingResolutionReturnsTheMatchedServedSpelling(): void
    {
        $matcher = new LocaleMatcher();

        self::assertSame('en_US', $matcher->resolveServedLocaleOrFail('en-GB', ['en_US']));
    }

    public function testAServedLocaleIsStillMatchedWhenIcuDoesNotKnowItsRegion(): void
    {
        $matcher = new LocaleMatcher();

        self::assertSame('en_XX', $matcher->resolveServedLocaleOrFail('en_US', ['en_XX']));
    }

    /**
     * @param list<string> $servedLocales
     */
    #[DataProvider('provideUnservedLocales')]
    public function testAnUnservedLocaleFails(string $requested, array $servedLocales): void
    {
        $matcher = new LocaleMatcher();

        $this->expectException(InvalidLocaleException::class);
        $this->expectExceptionMessage(sprintf('Locale "%s" is not served.', $requested));

        $matcher->resolveServedLocaleOrFail($requested, $servedLocales);
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function provideUnservedLocales(): iterable
    {
        yield 'another language' => ['de_AT', ['cs_CZ']];
        yield 'nothing served' => ['cs_CZ', []];
        yield 'blank served locale' => ['en_US', ['']];
    }
}

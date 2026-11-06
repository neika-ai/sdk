<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\Pairing;

use Neika\Sdk\Pairing\HostMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HostMatcherTest extends TestCase
{
    #[DataProvider('provideHosts')]
    public function testNormalize(string $hostOrUrl, ?string $expected): void
    {
        $matcher = new HostMatcher();

        self::assertSame($expected, $matcher->normalize($hostOrUrl));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function provideHosts(): iterable
    {
        yield 'bare host' => ['shop.cz', 'shop.cz'];
        yield 'url with path' => ['https://shop.cz/chatbot/pair?code=abc', 'shop.cz'];
        yield 'url with port' => ['https://shop.cz:8443', 'shop.cz'];
        yield 'surrounding whitespace' => ['  shop.cz  ', 'shop.cz'];
        yield 'uppercase' => ['HTTPS://Shop.CZ', 'shop.cz'];
        yield 'trailing dot' => ['shop.cz.', 'shop.cz'];
        yield 'idn' => ['https://žluťoučký-kůň.cz', 'xn--luouk-k-z2a6lsyxjlexh.cz'];
        yield 'uppercase idn' => ['ŽLUŤOUČKÝ-KŮŇ.CZ', 'xn--luouk-k-z2a6lsyxjlexh.cz'];
        yield 'www kept' => ['www.shop.cz', 'www.shop.cz'];
        yield 'empty' => ['', null];
        yield 'whitespace only' => ['   ', null];
        yield 'scheme only' => ['https://', null];
        yield 'dot only' => ['.', null];
        yield 'label too long' => [str_repeat('a', 64) . '.cz', null];
    }

    #[DataProvider('provideHostPairs')]
    public function testIsSameHost(string $hostOrUrl, string $otherHostOrUrl, bool $expected): void
    {
        $matcher = new HostMatcher();

        self::assertSame($expected, $matcher->isSameHost($hostOrUrl, $otherHostOrUrl));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function provideHostPairs(): iterable
    {
        yield 'url and bare host' => ['https://shop.cz/', 'shop.cz', true];
        yield 'uppercase and trailing dot' => ['SHOP.CZ.', 'shop.cz', true];
        yield 'idn and punycode' => ['https://žluťoučký-kůň.cz', 'xn--luouk-k-z2a6lsyxjlexh.cz', true];
        yield 'www differs' => ['www.shop.cz', 'shop.cz', false];
        yield 'subdomain differs' => ['eshop.shop.cz', 'shop.cz', false];
        yield 'lookalike' => ['evilshop.cz', 'shop.cz', false];
        yield 'both garbage' => ['', 'https://', false];
    }

    #[DataProvider('provideCoverage')]
    public function testIsCoveredByDomain(string $hostOrUrl, string $domain, bool $expected): void
    {
        $matcher = new HostMatcher();

        self::assertSame($expected, $matcher->isCoveredByDomain($hostOrUrl, $domain));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function provideCoverage(): iterable
    {
        yield 'same host' => ['https://shop.cz', 'shop.cz', true];
        yield 'www host' => ['https://www.shop.cz', 'shop.cz', true];
        yield 'www domain' => ['shop.cz', 'www.shop.cz', true];
        yield 'subdomain' => ['https://en.shop.cz/path', 'shop.cz', true];
        yield 'uppercase and trailing dot' => ['EN.SHOP.CZ.', 'Shop.Cz', true];
        yield 'idn subdomain' => ['eshop.žluťoučký-kůň.cz', 'xn--luouk-k-z2a6lsyxjlexh.cz', true];
        yield 'parent of domain' => ['shop.cz', 'en.shop.cz', false];
        yield 'lookalike' => ['evilshop.cz', 'shop.cz', false];
        yield 'lookalike suffix' => ['shop.cz.evil.com', 'shop.cz', false];
        yield 'garbage host' => ['https://', 'shop.cz', false];
        yield 'garbage domain' => ['shop.cz', '', false];
    }
}

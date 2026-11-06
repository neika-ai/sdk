<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\Telemetry;

use Neika\Sdk\Telemetry\ClickId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClickIdTest extends TestCase
{
    public function testTheQueryParameterIsNeikaclid(): void
    {
        self::assertSame('neikaclid', (new ClickId())->getQueryParameterName());
    }

    public function testAClickIdOfUpToSixtyFourCharactersIsFound(): void
    {
        $clickId = str_repeat('a', 64);

        self::assertSame($clickId, (new ClickId())->find(['neikaclid' => $clickId, 'page' => '2']));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideQueriesWithoutAUsableClickId(): iterable
    {
        yield 'missing' => [['page' => '2']];
        yield 'empty' => [['neikaclid' => '']];
        yield 'too long' => [['neikaclid' => str_repeat('a', 65)]];
        yield 'array' => [['neikaclid' => ['abc']]];
        yield 'null' => [['neikaclid' => null]];
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('provideQueriesWithoutAUsableClickId')]
    public function testNoUsableClickIdIsNull(array $query): void
    {
        self::assertNull((new ClickId())->find($query));
    }
}

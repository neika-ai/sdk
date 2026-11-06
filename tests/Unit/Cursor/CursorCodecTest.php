<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\Cursor;

use Neika\Sdk\Cursor\CursorCodec;
use Neika\Sdk\Exception\InvalidCursorException;
use PHPUnit\Framework\TestCase;

class CursorCodecTest extends TestCase
{
    private CursorCodec $codec;

    protected function setUp(): void
    {
        $this->codec = new CursorCodec();
    }

    public function testRoundTrip(): void
    {
        $cursor = $this->codec->encode('42');

        self::assertSame('42', $this->codec->decode($cursor));
        self::assertSame(42, $this->codec->decodeNumericId($cursor));
    }

    public function testNullCursorDecodesToNull(): void
    {
        self::assertNull($this->codec->decode(null));
        self::assertNull($this->codec->decodeNumericId(null));
    }

    public function testGarbageCursorThrows(): void
    {
        $this->expectException(InvalidCursorException::class);

        $this->codec->decode('!!!not-base64!!!');
    }

    public function testNonNumericDecodedCursorThrowsForNumericIds(): void
    {
        $this->expectException(InvalidCursorException::class);

        $this->codec->decodeNumericId(base64_encode('abc'));
    }
}

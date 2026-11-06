<?php

declare(strict_types=1);

namespace Neika\Sdk\Cursor;

use Neika\Sdk\Exception\InvalidCursorException;

class CursorCodec
{
    public function encode(string $lastId): string
    {
        return base64_encode($lastId);
    }

    public function decode(?string $cursor): ?string
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $decoded = base64_decode($cursor, true);
        if ($decoded === false || $decoded === '') {
            throw new InvalidCursorException($cursor);
        }

        return $decoded;
    }

    public function decodeNumericId(?string $cursor): ?int
    {
        $decoded = $this->decode($cursor);
        if ($decoded === null) {
            return null;
        }

        $isNumeric = ctype_digit($decoded);
        if (!$isNumeric) {
            throw new InvalidCursorException((string) $cursor);
        }

        return (int) $decoded;
    }
}

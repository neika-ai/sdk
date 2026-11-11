<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\Fixtures;

class PackerIdentity
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public readonly string $subject,
        public readonly string $storageUnit,
        public readonly array $roles = [],
    ) {
    }
}

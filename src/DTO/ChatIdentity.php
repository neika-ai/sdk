<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

use Neika\Sdk\Exception\IdentityMappingException;

class ChatIdentity
{
    /**
     * @param array<string, mixed> $claims
     */
    public function __construct(
        public readonly string $subject,
        public readonly array $claims = [],
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function mapTo(string $class): object
    {
        $arguments = ['subject' => $this->subject] + $this->claims;

        try {
            return new $class(...$arguments);
        } catch (\Error $error) {
            throw new IdentityMappingException($class, $error);
        }
    }
}

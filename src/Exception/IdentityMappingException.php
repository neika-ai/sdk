<?php

declare(strict_types=1);

namespace Neika\Sdk\Exception;

class IdentityMappingException extends \LogicException
{
    public function __construct(string $class, \Throwable $previous)
    {
        parent::__construct(sprintf('The identity does not fit the constructor of "%s", got "%s".', $class, $previous->getMessage()), 0, $previous);
    }
}

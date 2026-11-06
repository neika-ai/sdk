<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\Fixtures;

use Neika\Sdk\Validator\Sensitive;
use Symfony\Component\Validator\Constraints as Assert;

class ContactArguments
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 32)]
        public readonly string $orderNumber = '',
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Sensitive]
        public readonly string $email = '',
    ) {
    }
}

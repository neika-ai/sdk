<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ToolListHeaders
{
    public function __construct(
        #[Assert\Locale]
        public readonly ?string $locale = null,
    ) {
    }
}

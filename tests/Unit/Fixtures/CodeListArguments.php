<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

class CodeListArguments
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Count(min: 1, max: 20)]
        #[Assert\All([new Assert\Type('string'), new Assert\Length(max: 64)])]
        public readonly array $codes = [],
    ) {
    }
}

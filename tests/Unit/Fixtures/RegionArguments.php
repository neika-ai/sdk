<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\Fixtures;

use Neika\Sdk\Validator\ToolChoice;

class RegionArguments
{
    public function __construct(
        #[ToolChoice(loader: RegionChoiceLoader::class)]
        public readonly ?string $region = null,
    ) {
    }
}

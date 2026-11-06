<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\Fixtures;

use Neika\Sdk\Validator\ToolChoice;

class MistypedRegionArguments
{
    public function __construct(
        #[ToolChoice(loader: RegionChoiceLoader::class)]
        public readonly int $region = 0,
    ) {
    }
}

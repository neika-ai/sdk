<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\Fixtures;

use Neika\Sdk\Contract\ToolChoiceLoaderInterface;

class RegionChoiceLoader implements ToolChoiceLoaderInterface
{
    /**
     * @param list<string> $choices
     */
    public function __construct(
        private readonly array $choices = ['Praha', 'Moravskoslezský kraj'],
    ) {
    }

    public function loadChoices(): array
    {
        return $this->choices;
    }
}

<?php

declare(strict_types=1);

namespace Neika\Sdk\Contract;

interface ToolChoiceLoaderInterface
{
    /**
     * @return list<string>
     */
    public function loadChoices(): array;
}

<?php

declare(strict_types=1);

namespace Neika\Sdk\Registry;

use Neika\Sdk\Contract\ChatbotToolInterface;

class ToolRegistry
{
    /**
     * @param iterable<ChatbotToolInterface> $tools
     */
    public function __construct(
        private readonly iterable $tools,
    ) {
    }

    public function get(string $name): ?ChatbotToolInterface
    {
        foreach ($this->tools as $tool) {
            $definitionName = $tool->getDefinition()->name;
            if ($definitionName === $name) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * @return iterable<ChatbotToolInterface>
     */
    public function all(): iterable
    {
        yield from $this->tools;
    }
}

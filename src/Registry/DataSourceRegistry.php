<?php

declare(strict_types=1);

namespace Neika\Sdk\Registry;

use Neika\Sdk\Contract\ChatbotDataSourceInterface;

class DataSourceRegistry
{
    /**
     * @param iterable<ChatbotDataSourceInterface> $sources
     */
    public function __construct(
        private readonly iterable $sources,
    ) {
    }

    public function get(string $name): ?ChatbotDataSourceInterface
    {
        foreach ($this->sources as $source) {
            $definitionName = $source->getDefinition()->name;
            if ($definitionName === $name) {
                return $source;
            }
        }

        return null;
    }

    /**
     * @return iterable<ChatbotDataSourceInterface>
     */
    public function all(): iterable
    {
        yield from $this->sources;
    }
}

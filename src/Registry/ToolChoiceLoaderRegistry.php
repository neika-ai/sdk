<?php

declare(strict_types=1);

namespace Neika\Sdk\Registry;

use Neika\Sdk\Contract\ToolChoiceLoaderInterface;

class ToolChoiceLoaderRegistry
{
    /**
     * @param iterable<ToolChoiceLoaderInterface> $loaders
     */
    public function __construct(
        private readonly iterable $loaders,
    ) {
    }

    /**
     * @param class-string<ToolChoiceLoaderInterface> $loaderClass
     *
     * @return list<string>
     */
    public function getChoices(string $loaderClass): array
    {
        $loader = $this->findLoader($loaderClass);
        if ($loader === null) {
            throw new \LogicException(sprintf(
                'The tool choice loader "%s" is not registered as a %s.',
                $loaderClass,
                ToolChoiceLoaderInterface::class,
            ));
        }

        $choices = $loader->loadChoices();

        return array_values(array_unique($choices));
    }

    /**
     * @param class-string<ToolChoiceLoaderInterface> $loaderClass
     */
    private function findLoader(string $loaderClass): ?ToolChoiceLoaderInterface
    {
        foreach ($this->loaders as $loader) {
            $matches = $loader::class === $loaderClass;
            if ($matches) {
                return $loader;
            }
        }

        return null;
    }
}

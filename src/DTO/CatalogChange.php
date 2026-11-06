<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

use Neika\Sdk\Enum\CatalogSourceName;

class CatalogChange implements \JsonSerializable
{
    /**
     * @param list<string> $externalIds
     */
    public function __construct(
        public readonly CatalogSourceName $source,
        public readonly string            $locale,
        public readonly array             $externalIds,
    ) {
    }

    /**
     * @return array{source: string, locale: string, externalIds: list<string>}
     */
    public function jsonSerialize(): array
    {
        return [
            'source' => $this->source->value,
            'locale' => $this->locale,
            'externalIds' => array_values($this->externalIds),
        ];
    }
}

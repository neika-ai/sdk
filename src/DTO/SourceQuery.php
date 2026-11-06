<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class SourceQuery
{
    /**
     * @param ?list<string> $ids
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Locale]
        public readonly string $locale = '',

        #[Assert\Length(max: 255)]
        public readonly ?string $channel = null,

        #[Assert\Length(max: 255)]
        public readonly ?string $cursor = null,

        #[Assert\Count(max: 500)]
        #[Assert\Unique]
        #[Assert\All([
            new Assert\Type('string'),
            new Assert\NotBlank(),
            new Assert\Length(max: 255),
        ])]
        public readonly ?array $ids = null,
    ) {
    }

    public function withLocale(string $locale): self
    {
        return new self($locale, $this->channel, $this->cursor, $this->ids);
    }

    public function hasIds(): bool
    {
        return $this->ids !== null && $this->ids !== [];
    }
}

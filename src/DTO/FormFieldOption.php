<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

use Symfony\Contracts\Translation\TranslatorInterface;

class FormFieldOption implements \JsonSerializable
{
    public function __construct(
        public readonly string $value,
        public readonly string $label,
    ) {
    }

    public function translated(TranslatorInterface $translator, string $locale, ?string $domain = null): self
    {
        return new self($this->value, $translator->trans($this->label, [], $domain, $locale));
    }

    public function jsonSerialize(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label,
        ];
    }
}

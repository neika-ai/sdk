<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

use Symfony\Contracts\Translation\TranslatorInterface;

class FormDefinition implements \JsonSerializable
{
    public function __construct(
        public readonly string $formId,
        public readonly string $title,
        public readonly array $fields,
        public readonly string $submitLabel,
    ) {
    }

    public function translated(TranslatorInterface $translator, string $locale, ?string $domain = null): self
    {
        return new self(
            $this->formId,
            $translator->trans($this->title, [], $domain, $locale),
            array_map(
                fn (FormField $field): FormField => $field->translated($translator, $locale, $domain),
                $this->fields,
            ),
            $translator->trans($this->submitLabel, [], $domain, $locale),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'formId' => $this->formId,
            'title' => $this->title,
            'fields' => array_map(
                fn (FormField $field): array => $field->jsonSerialize(),
                $this->fields,
            ),
            'submitLabel' => $this->submitLabel,
        ];
    }
}

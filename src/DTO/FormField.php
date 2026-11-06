<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

use Neika\Sdk\Enum\FormFieldType;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormField implements \JsonSerializable
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly FormFieldType $type,
        public readonly bool $required,
        public readonly ?array $options = null,
        public readonly ?string $placeholder = null,
    ) {
    }

    public function translated(TranslatorInterface $translator, string $locale, ?string $domain = null): self
    {
        $translatedOptions = null;
        if ($this->options !== null) {
            $translatedOptions = array_map(
                fn (FormFieldOption $option): FormFieldOption => $option->translated($translator, $locale, $domain),
                $this->options,
            );
        }

        return new self(
            $this->name,
            $translator->trans($this->label, [], $domain, $locale),
            $this->type,
            $this->required,
            $translatedOptions,
            $this->placeholder,
        );
    }

    public function jsonSerialize(): array
    {
        $serialized = [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type->value,
            'required' => $this->required,
        ];

        if ($this->options !== null) {
            $serialized['options'] = array_map(
                fn (FormFieldOption $option): array => $option->jsonSerialize(),
                $this->options,
            );
        }

        if ($this->placeholder !== null) {
            $serialized['placeholder'] = $this->placeholder;
        }

        return $serialized;
    }
}

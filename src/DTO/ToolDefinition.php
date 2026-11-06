<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

use Symfony\Contracts\Translation\TranslatorInterface;

class ToolDefinition implements \JsonSerializable
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly ?ToolUiHints $ui = null,
        public readonly ?array $inputSchema = null,
        public readonly ?string $translationDomain = null,
    ) {
    }

    public function withInputSchema(array $inputSchema): self
    {
        return new self($this->name, $this->description, $this->ui, $inputSchema, $this->translationDomain);
    }

    public function translated(TranslatorInterface $translator, string $locale): self
    {
        return new self(
            $this->name,
            $translator->trans($this->description, [], $this->translationDomain, $locale),
            $this->ui?->translated($translator, $locale, $this->translationDomain),
            $this->inputSchema,
            $this->translationDomain,
        );
    }

    public function jsonSerialize(): array
    {
        $serialized = [
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema ?? ['type' => 'object', 'additionalProperties' => false],
        ];

        if ($this->ui !== null) {
            $serialized['ui'] = $this->ui->jsonSerialize();
        }

        return $serialized;
    }
}

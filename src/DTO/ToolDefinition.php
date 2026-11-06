<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

use Symfony\Contracts\Translation\TranslatorInterface;

class ToolDefinition implements \JsonSerializable
{
    /**
     * @param list<string> $sensitiveArguments
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly ?ToolUiHints $ui = null,
        public readonly ?array $inputSchema = null,
        public readonly ?string $translationDomain = null,
        public readonly array $sensitiveArguments = [],
    ) {
    }

    public function withInputSchema(array $inputSchema): self
    {
        return new self(
            $this->name,
            $this->description,
            $this->ui,
            $inputSchema,
            $this->translationDomain,
            $this->sensitiveArguments,
        );
    }

    /**
     * @param list<string> $sensitiveArguments
     */
    public function withSensitiveArguments(array $sensitiveArguments): self
    {
        return new self(
            $this->name,
            $this->description,
            $this->ui,
            $this->inputSchema,
            $this->translationDomain,
            $sensitiveArguments,
        );
    }

    public function translated(TranslatorInterface $translator, string $locale): self
    {
        return new self(
            $this->name,
            $translator->trans($this->description, [], $this->translationDomain, $locale),
            $this->ui?->translated($translator, $locale, $this->translationDomain),
            $this->inputSchema,
            $this->translationDomain,
            $this->sensitiveArguments,
        );
    }

    public function jsonSerialize(): array
    {
        $serialized = [
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema ?? ['type' => 'object', 'additionalProperties' => false],
            'sensitiveArguments' => $this->sensitiveArguments,
        ];

        if ($this->ui !== null) {
            $serialized['ui'] = $this->ui->jsonSerialize();
        }

        return $serialized;
    }
}

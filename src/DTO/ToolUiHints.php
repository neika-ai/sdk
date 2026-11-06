<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

use Symfony\Contracts\Translation\TranslatorInterface;

class ToolUiHints implements \JsonSerializable
{
    public function __construct(
        public readonly FormDefinition $form,
    ) {
    }

    public function translated(TranslatorInterface $translator, string $locale, ?string $domain = null): self
    {
        return new self($this->form->translated($translator, $locale, $domain));
    }

    public function jsonSerialize(): array
    {
        return [
            'form' => $this->form->jsonSerialize(),
        ];
    }
}

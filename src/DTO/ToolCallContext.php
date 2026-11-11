<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

use Neika\Sdk\Exception\IdentityRequiredException;
use Symfony\Component\Validator\Constraints as Assert;

class ToolCallContext implements \JsonSerializable
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public readonly string $conversationId,
        #[Assert\NotBlank]
        #[Assert\Locale]
        public readonly string $locale,
        public readonly ?string $channelCode = null,
        private readonly ?ChatIdentity $identity = null,
    ) {
    }

    public function withLocale(string $locale): self
    {
        return new self($this->conversationId, $locale, $this->channelCode, $this->identity);
    }

    public function hasIdentity(): bool
    {
        return $this->identity !== null;
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|null $class
     *
     * @return ($class is null ? ChatIdentity : T)
     */
    public function getIdentity(?string $class = null): object
    {
        $identity = $this->identity ?? throw new IdentityRequiredException();
        if ($class === null) {
            return $identity;
        }

        return $identity->mapTo($class);
    }

    public function jsonSerialize(): array
    {
        $serialized = [
            'conversationId' => $this->conversationId,
            'locale' => $this->locale,
            'channelCode' => $this->channelCode,
        ];

        if ($this->identity !== null) {
            $serialized['identity'] = [
                'subject' => $this->identity->subject,
                'claims' => (object) $this->identity->claims,
            ];
        }

        return $serialized;
    }
}

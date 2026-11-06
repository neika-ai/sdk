<?php

declare(strict_types=1);

namespace Neika\Sdk\Contract;

interface ChatbotLocaleContextInterface
{
    public function applyChannel(?string $channelCode): void;

    public function getCurrentLocale(): string;

    public function getDefaultLocale(): string;

    /**
     * @return list<string>
     */
    public function getChannelLocales(): array;

    /**
     * @invariant Every channel's locales, not just the resolved one: the source list has no channel,
     *            and a backend reading several channels through one host needs them all.
     *
     * @return list<string>
     */
    public function getAllChannelLocales(): array;

    public function resolveForChannel(string $requestedLocale): ?string;
}

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
     * @invariant Answers for every channel, not the one the request resolved. The source list
     *            carries no channel, so a backend addressing several channels through a single
     *            host must still learn every locale it may later read.
     *
     * @return list<string>
     */
    public function getAllChannelLocales(): array;

    public function resolveForChannel(string $requestedLocale): ?string;
}

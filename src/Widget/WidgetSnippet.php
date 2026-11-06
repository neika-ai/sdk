<?php

declare(strict_types=1);

namespace Neika\Sdk\Widget;

class WidgetSnippet
{
    public function render(
        string $backendUrl,
        string $siteKey,
        string $cdnUrl = '',
        string $locale = '',
        bool $defer = true,
    ): string {
        $scriptUrl = $this->resolveScriptUrl($cdnUrl);
        $deferAttribute = $defer ? ' defer' : '';

        $script = sprintf('<script src="%s"%s></script>', $this->escape($scriptUrl), $deferAttribute);
        $element = $this->renderElement($backendUrl, $siteKey, $locale);

        return $script . "\n" . $element;
    }

    public function getDefaultCdnUrl(): string
    {
        return 'https://neika.b-cdn.net/widget/v1/chat.js';
    }

    private function resolveScriptUrl(string $cdnUrl): string
    {
        $hasCdnUrl = $cdnUrl !== '';
        if ($hasCdnUrl) {
            return $cdnUrl;
        }

        return $this->getDefaultCdnUrl();
    }

    private function renderElement(string $backendUrl, string $siteKey, string $locale): string
    {
        $localeAttribute = '';
        $hasLocale = $locale !== '';
        if ($hasLocale) {
            $localeAttribute = sprintf(' locale="%s"', $this->escape($locale));
        }

        return sprintf(
            '<ai-chat-widget site-key="%s"%s backend-url="%s"></ai-chat-widget>',
            $this->escape($siteKey),
            $localeAttribute,
            $this->escape($backendUrl),
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

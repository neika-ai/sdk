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
        string $identityToken = '',
        string $identityTokenUrl = '',
    ): string {
        $scriptUrl = $this->resolveScriptUrl($cdnUrl);
        $deferAttribute = $defer ? ' defer' : '';

        $script = sprintf('<script src="%s"%s></script>', $this->escape($scriptUrl), $deferAttribute);
        $element = $this->renderElement([
            'site-key' => $siteKey,
            'locale' => $locale,
            'backend-url' => $backendUrl,
            'identity-token' => $identityToken,
            'identity-token-url' => $identityTokenUrl,
        ]);

        return $script . "\n" . $element;
    }

    public function getDefaultCdnUrl(): string
    {
        return 'https://neika.b-cdn.net/widget/neika-chatbot.js';
    }

    private function resolveScriptUrl(string $cdnUrl): string
    {
        $hasCdnUrl = $cdnUrl !== '';
        if ($hasCdnUrl) {
            return $cdnUrl;
        }

        return $this->getDefaultCdnUrl();
    }

    /**
     * @return list<string>
     */
    private function getAlwaysRenderedAttributes(): array
    {
        return ['site-key', 'backend-url'];
    }

    /**
     * @param array<string, string> $attributes
     */
    private function renderElement(array $attributes): string
    {
        $rendered = '';

        foreach ($attributes as $name => $value) {
            $isAlwaysRendered = in_array($name, $this->getAlwaysRenderedAttributes(), true);
            $hasValue = $value !== '';
            if ($isAlwaysRendered || $hasValue) {
                $rendered .= sprintf(' %s="%s"', $name, $this->escape($value));
            }
        }

        return sprintf('<neika-chatbot-widget%s></neika-chatbot-widget>', $rendered);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

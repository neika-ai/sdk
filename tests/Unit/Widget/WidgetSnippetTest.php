<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\Widget;

use Neika\Sdk\Widget\WidgetSnippet;
use PHPUnit\Framework\TestCase;

class WidgetSnippetTest extends TestCase
{
    public function testItLoadsTheScriptFromTheNeikaCdnWhenNoCdnIsGiven(): void
    {
        $markup = (new WidgetSnippet())->render('https://neika.test/', 'pk_site');

        self::assertStringContainsString('<script src="https://neika.b-cdn.net/widget/neika-chatbot.js" defer></script>', $markup);
        self::assertStringContainsString('site-key="pk_site"', $markup);
        self::assertStringContainsString('backend-url="https://neika.test/"', $markup);
        self::assertStringNotContainsString('locale=', $markup);
    }

    public function testTheScriptLoadsBlockingWhenDeferIsOff(): void
    {
        $markup = (new WidgetSnippet())->render('https://neika.test', 'pk_site', defer: false);

        self::assertStringContainsString('<script src="https://neika.b-cdn.net/widget/neika-chatbot.js"></script>', $markup);
    }

    public function testTheCdnUrlOverridesTheScriptSource(): void
    {
        $markup = (new WidgetSnippet())->render('https://neika.test', 'pk_site', 'https://cdn.test/widget/neika-chatbot.js');

        self::assertStringContainsString('src="https://cdn.test/widget/neika-chatbot.js"', $markup);
    }

    public function testTheLocaleAttributeIsEmittedOnlyWhenGiven(): void
    {
        $markup = (new WidgetSnippet())->render('https://neika.test', 'pk_site', '', 'cs_CZ');

        self::assertStringContainsString('<neika-chatbot-widget site-key="pk_site" locale="cs_CZ" backend-url="https://neika.test"></neika-chatbot-widget>', $markup);
    }

    public function testAttributeValuesAreEscaped(): void
    {
        $markup = (new WidgetSnippet())->render('https://neika.test', 'pk"><script>alert(1)</script>');

        self::assertStringNotContainsString('<script>alert(1)', $markup);
        self::assertStringContainsString('&quot;', $markup);
    }
}

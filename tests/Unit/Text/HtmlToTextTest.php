<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\Text;

use Neika\Sdk\Text\HtmlToText;
use PHPUnit\Framework\TestCase;

class HtmlToTextTest extends TestCase
{
    private HtmlToText $converter;

    protected function setUp(): void
    {
        $this->converter = new HtmlToText();
    }

    public function testListItemsBecomeBulletLines(): void
    {
        $text = $this->converter->convert('<ul><li>First item</li><li>Second item</li></ul>');

        self::assertStringContainsString("- First item\n- Second item", $text);
    }

    public function testEntitiesAreDecoded(): void
    {
        $text = $this->converter->convert('<p>Caf&eacute; &amp; bar&nbsp;area</p>');

        self::assertSame('Café & bar area', $text);
    }

    public function testHeadingsStayOnOwnLine(): void
    {
        $text = $this->converter->convert('<h2>Delivery</h2><p>Within 3 days.</p>');

        self::assertSame("Delivery\nWithin 3 days.", $text);
    }

    public function testScriptsAndTagsAreStripped(): void
    {
        $text = $this->converter->convert('<div>Visible<script>alert(1)</script></div>');

        self::assertSame('Visible', $text);
    }
}

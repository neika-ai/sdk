<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\Registry;

use Neika\Sdk\Registry\ToolChoiceLoaderRegistry;
use Neika\Sdk\Tests\Unit\Fixtures\RegionChoiceLoader;
use PHPUnit\Framework\TestCase;

class ToolChoiceLoaderRegistryTest extends TestCase
{
    public function testReturnsTheChoicesOfTheRegisteredLoader(): void
    {
        $registry = new ToolChoiceLoaderRegistry([new RegionChoiceLoader()]);

        self::assertSame(['Praha', 'Moravskoslezský kraj'], $registry->getChoices(RegionChoiceLoader::class));
    }

    public function testReturnsEachChoiceOnceAsAList(): void
    {
        $registry = new ToolChoiceLoaderRegistry([new RegionChoiceLoader(['Praha', 'Praha', 'Vysočina'])]);

        self::assertSame(['Praha', 'Vysočina'], $registry->getChoices(RegionChoiceLoader::class));
    }

    public function testRefusesALoaderThatIsNotRegistered(): void
    {
        $registry = new ToolChoiceLoaderRegistry([]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(RegionChoiceLoader::class);

        $registry->getChoices(RegionChoiceLoader::class);
    }
}

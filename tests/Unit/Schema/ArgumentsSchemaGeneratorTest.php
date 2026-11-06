<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\Schema;

use Neika\Sdk\Registry\ToolChoiceLoaderRegistry;
use Neika\Sdk\Schema\ArgumentsSchemaGenerator;
use Neika\Sdk\Tests\Unit\Fixtures\CodeListArguments;
use Neika\Sdk\Tests\Unit\Fixtures\ContactArguments;
use Neika\Sdk\Tests\Unit\Fixtures\MistypedRegionArguments;
use Neika\Sdk\Tests\Unit\Fixtures\NullableArguments;
use Neika\Sdk\Tests\Unit\Fixtures\RegionArguments;
use Neika\Sdk\Tests\Unit\Fixtures\RegionChoiceLoader;
use Neika\Sdk\Tests\Unit\Fixtures\RegionListArguments;
use PHPUnit\Framework\TestCase;

class ArgumentsSchemaGeneratorTest extends TestCase
{
    private ArgumentsSchemaGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ArgumentsSchemaGenerator(
            new ToolChoiceLoaderRegistry([new RegionChoiceLoader()]),
        );
    }

    public function testToolChoicePropertyListsTheLoadedChoicesAsItsEnum(): void
    {
        $schema = $this->generator->generate(RegionArguments::class);

        self::assertSame(
            ['type' => 'string', 'enum' => ['Praha', 'Moravskoslezský kraj']],
            $schema['properties']['region'],
        );
        self::assertArrayNotHasKey('required', $schema);
    }

    public function testMultipleToolChoicePropertyListsTheLoadedChoicesAsItsItemEnum(): void
    {
        $schema = $this->generator->generate(RegionListArguments::class);

        self::assertSame(
            [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => ['Praha', 'Moravskoslezský kraj']],
                'minItems' => 1,
                'maxItems' => 2,
            ],
            $schema['properties']['regions'],
        );
    }

    public function testToolChoiceOnAPropertyOfTheWrongTypeIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(MistypedRegionArguments::class . '::$region" must be typed "string"');

        $this->generator->generate(MistypedRegionArguments::class);
    }

    public function testToolChoicePropertyIsLeftOutWhenTheLoaderOffersNothing(): void
    {
        $generator = new ArgumentsSchemaGenerator(
            new ToolChoiceLoaderRegistry([new RegionChoiceLoader([])]),
        );

        $schema = $generator->generate(RegionArguments::class);

        self::assertSame(['type' => 'object', 'additionalProperties' => false], $schema);
    }

    public function testRequiredStringPropertiesCarryLengthAndFormat(): void
    {
        $schema = $this->generator->generate(ContactArguments::class);

        self::assertSame('object', $schema['type']);
        self::assertSame(['orderNumber', 'email'], $schema['required']);
        self::assertSame('string', $schema['properties']['orderNumber']['type']);
        self::assertSame(32, $schema['properties']['orderNumber']['maxLength']);
        self::assertSame('email', $schema['properties']['email']['format']);
        self::assertFalse($schema['additionalProperties']);
    }

    public function testOnlyPropertiesMarkedSensitiveAreListedAsSensitiveArguments(): void
    {
        $sensitiveArguments = $this->generator->listSensitiveArguments(ContactArguments::class);

        self::assertSame(['email'], $sensitiveArguments);
    }

    public function testNullablePropertyIsNotRequired(): void
    {
        $schema = $this->generator->generate(NullableArguments::class);

        self::assertSame(['query'], $schema['required']);
        self::assertArrayHasKey('note', $schema['properties']);
        self::assertSame(['relevance', 'price'], $schema['properties']['sort']['enum']);
    }

    public function testArrayPropertySchema(): void
    {
        $schema = $this->generator->generate(CodeListArguments::class);

        self::assertSame('array', $schema['properties']['codes']['type']);
        self::assertSame('string', $schema['properties']['codes']['items']['type']);
        self::assertSame(['codes'], $schema['required']);
    }
}

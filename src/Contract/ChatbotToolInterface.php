<?php

declare(strict_types=1);

namespace Neika\Sdk\Contract;

use Neika\Sdk\DTO\ToolCallContext;
use Neika\Sdk\DTO\ToolDefinition;
use Neika\Sdk\DTO\ToolResult;

interface ChatbotToolInterface
{
    public function getDefinition(): ToolDefinition;

    public function getArgumentsClass(): string;

    public function execute(object $arguments, ToolCallContext $context): ToolResult;
}

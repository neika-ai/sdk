<?php

declare(strict_types=1);

namespace Neika\Sdk\Contract;

use Neika\Sdk\DTO\DocumentPage;
use Neika\Sdk\DTO\SourceDefinition;
use Neika\Sdk\DTO\SourceQuery;

interface ChatbotDataSourceInterface
{
    public function getDefinition(): SourceDefinition;

    public function getDocuments(SourceQuery $query): DocumentPage;
}

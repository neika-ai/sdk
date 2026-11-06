<?php

declare(strict_types=1);

namespace Neika\Sdk\DTO;

use Neika\Sdk\Enum\CatalogJobStatus;

class CatalogChangeJob
{
    public function __construct(
        public readonly string           $externalId,
        public readonly ?string          $jobId,
        public readonly CatalogJobStatus $status,
        public readonly ?string          $violation = null,
    ) {
    }

    public function isRejected(): bool
    {
        return $this->status === CatalogJobStatus::Rejected;
    }
}

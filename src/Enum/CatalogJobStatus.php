<?php

declare(strict_types=1);

namespace Neika\Sdk\Enum;

enum CatalogJobStatus: string
{
    case Queued = 'queued';
    case Rejected = 'rejected';
    case Unknown = 'unknown';
}

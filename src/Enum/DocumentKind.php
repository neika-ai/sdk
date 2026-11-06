<?php

declare(strict_types=1);

namespace Neika\Sdk\Enum;

enum DocumentKind: string
{
    case Page = 'page';
    case Faq = 'faq';
    case Product = 'product';
    case Category = 'category';
    case Other = 'other';
}

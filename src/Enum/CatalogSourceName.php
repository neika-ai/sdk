<?php

declare(strict_types=1);

namespace Neika\Sdk\Enum;

enum CatalogSourceName: string
{
    case Products = 'products';
    case Categories = 'categories';
    case CmsPages = 'cms_pages';
}

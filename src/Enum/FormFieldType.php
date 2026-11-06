<?php

declare(strict_types=1);

namespace Neika\Sdk\Enum;

enum FormFieldType: string
{
    case Text = 'text';
    case Email = 'email';
    case Number = 'number';
    case Select = 'select';
}

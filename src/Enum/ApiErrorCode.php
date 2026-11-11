<?php

declare(strict_types=1);

namespace Neika\Sdk\Enum;

enum ApiErrorCode: string
{
    case Unauthorized = 'unauthorized';
    case ToolNotFound = 'tool_not_found';
    case SourceNotFound = 'source_not_found';
    case ValidationFailed = 'validation_failed';
    case InvalidCursor = 'invalid_cursor';
    case InvalidLocale = 'invalid_locale';
    case InvalidChannel = 'invalid_channel';
    case IdentityRequired = 'identity_required';
    case AmbiguousTaxonTree = 'ambiguous_taxon_tree';
    case BadRequest = 'bad_request';
    case NotFound = 'not_found';
    case MethodNotAllowed = 'method_not_allowed';
    case InternalError = 'internal_error';

    public function getTranslationKey(): string
    {
        return 'neika.error.' . $this->value;
    }
}

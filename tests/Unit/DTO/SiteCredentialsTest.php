<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\DTO;

use Neika\Sdk\DTO\SiteCredentials;
use PHPUnit\Framework\TestCase;

class SiteCredentialsTest extends TestCase
{
    public function testCredentialsWithAnIngestSecretHaveOne(): void
    {
        $credentials = new SiteCredentials('pk_site', 'ingest-secret');

        self::assertTrue($credentials->hasIngestSecret());
    }

    public function testCredentialsWithAnEmptyIngestSecretHaveNone(): void
    {
        $credentials = new SiteCredentials('pk_site', '');

        self::assertFalse($credentials->hasIngestSecret());
    }
}

<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\Identity;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Neika\Sdk\DTO\ChatIdentity;
use Neika\Sdk\DTO\SiteCredentials;
use Neika\Sdk\DTO\ToolCallContext;
use Neika\Sdk\Exception\IdentityMappingException;
use Neika\Sdk\Identity\IdentityTokenIssuer;
use Neika\Sdk\Tests\Unit\Fixtures\PackerIdentity;
use PHPUnit\Framework\TestCase;

class IdentityTokenIssuerTest extends TestCase
{
    public function testTheTokenVerifiesWithTheIngestSecretAndCarriesTheIdentity(): void
    {
        $secret = bin2hex(random_bytes(32));
        $credentials = new SiteCredentials('pk_site', $secret);
        $identity = new ChatIdentity('admin-42', ['storageUnit' => 'B2', 'canShip' => true]);

        $token = (new IdentityTokenIssuer())->issue($credentials, $identity, 600);

        $payload = JWT::decode($token, new Key($secret, 'HS256'));
        self::assertSame('admin-42', $payload->sub);
        self::assertSame(600, $payload->exp - $payload->iat);
        self::assertSame(['storageUnit' => 'B2', 'canShip' => true], (array) $payload->claims);
    }

    public function testAToolReadsTheIdentityAsItsOwnClass(): void
    {
        $identity = new ChatIdentity('admin-42', ['storageUnit' => 'B2', 'roles' => ['packer']]);
        $context = new ToolCallContext('0199a1b2-0000-7000-8000-000000000001', 'cs_CZ', null, $identity);

        $packer = $context->getIdentity(PackerIdentity::class);

        self::assertSame('admin-42', $packer->subject);
        self::assertSame('B2', $packer->storageUnit);
        self::assertSame(['packer'], $packer->roles);
        self::assertSame($identity, $context->getIdentity());
        self::assertSame(
            '{"conversationId":"0199a1b2-0000-7000-8000-000000000001","locale":"cs_CZ","channelCode":null}',
            json_encode(new ToolCallContext('0199a1b2-0000-7000-8000-000000000001', 'cs_CZ')),
        );

        $this->expectException(IdentityMappingException::class);
        (new ChatIdentity('admin-42', ['unknownClaim' => 1]))->mapTo(PackerIdentity::class);
    }

    public function testALifetimeOutsideFiveMinutesToTwoHoursIsRefused(): void
    {
        $issuer = new IdentityTokenIssuer();
        $credentials = new SiteCredentials('pk_site', bin2hex(random_bytes(32)));
        $identity = new ChatIdentity('admin-42');
        $refused = 0;

        foreach ([299, 7201] as $ttlSeconds) {
            try {
                $issuer->issue($credentials, $identity, $ttlSeconds);
            } catch (\InvalidArgumentException) {
                ++$refused;
            }
        }

        self::assertSame(2, $refused);
        self::assertNotSame('', $issuer->issue($credentials, $identity, 300));
        self::assertNotSame('', $issuer->issue($credentials, $identity, 7200));
    }
}

<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\Pairing;

use Neika\Sdk\Exception\PairingException;
use Neika\Sdk\Pairing\PairingClient;
use Neika\Sdk\Tests\Unit\Fixtures\CapturingHttpClient;
use Neika\Sdk\Tests\Unit\Fixtures\TransportFailure;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PairingClientTest extends TestCase
{
    public function testASuccessfulPairingIsPostedAndMappedIntoTheResult(): void
    {
        $response = new Response(200, [], json_encode([
            'siteKey'         => 'pk_site',
            'baseUrl'         => 'https://shop.test',
            'channelCodes'    => ['FASHION_WEB', 'FASHION_DE'],
            'returnUrl'       => 'https://neika.test/pairing/018f-uuid/finish',
            'verifiedDomains' => ['shop.test', 'shop.de'],
        ]));
        $httpClient = new CapturingHttpClient($response);

        $result = $this->createClient($httpClient)->complete('raw-code', 'api-secret', 'ingest-secret');

        self::assertSame('pk_site', $result->siteKey);
        self::assertSame('https://shop.test', $result->baseUrl);
        self::assertSame(['FASHION_WEB', 'FASHION_DE'], $result->channelCodes);
        self::assertSame('https://neika.test/pairing/018f-uuid/finish', $result->returnUrl);
        self::assertSame(['shop.test', 'shop.de'], $result->verifiedDomains);

        $request = $httpClient->lastRequest;
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://neika.test/api/v1/tool-server-pairings/complete', (string) $request->getUri());
        self::assertSame(
            ['code' => 'raw-code', 'apiSecret' => 'api-secret', 'ingestSecret' => 'ingest-secret'],
            json_decode((string) $request->getBody(), true),
        );
    }

    public function testAnEmptyChannelListIsAccepted(): void
    {
        $response = new Response(200, [], json_encode([
            'siteKey'         => 'pk_site',
            'baseUrl'         => 'https://shop.test',
            'channelCodes'    => [],
            'returnUrl'       => 'https://neika.test/finish',
            'verifiedDomains' => ['shop.test'],
        ]));

        $result = $this->createClient(new CapturingHttpClient($response))->complete('raw-code', 'api-secret', 'ingest-secret');

        self::assertSame([], $result->channelCodes);
    }

    public function testAnUnknownCodeThrowsWithTheBackendErrorCode(): void
    {
        $response = new Response(404, [], json_encode([
            'error' => ['code' => 'pairing_not_found', 'message' => 'Pairing not found.'],
        ]));

        $errorCode = $this->captureErrorCode(new CapturingHttpClient($response));

        self::assertSame('pairing_not_found', $errorCode);
    }

    public function testARejectionWithoutAnErrorEnvelopeIsAnInvalidResponse(): void
    {
        $response = new Response(502, [], '<html>Bad gateway</html>');

        $errorCode = $this->captureErrorCode(new CapturingHttpClient($response));

        self::assertSame(PairingException::INVALID_RESPONSE, $errorCode);
    }

    public function testATransportFailureIsReportedAsTransportFailed(): void
    {
        $httpClient = new CapturingHttpClient(new Response(200), new TransportFailure('connection refused'));

        $errorCode = $this->captureErrorCode($httpClient);

        self::assertSame(PairingException::TRANSPORT_FAILED, $errorCode);
    }

    public function testMalformedJsonOnSuccessIsAnInvalidResponse(): void
    {
        $response = new Response(200, [], '{"siteKey": "pk_site",');

        $errorCode = $this->captureErrorCode(new CapturingHttpClient($response));

        self::assertSame(PairingException::INVALID_RESPONSE, $errorCode);
    }

    public function testASuccessWithANonStringChannelCodeIsAnInvalidResponse(): void
    {
        $response = new Response(200, [], json_encode([
            'siteKey'         => 'pk_site',
            'baseUrl'         => 'https://shop.test',
            'channelCodes'    => ['FASHION_WEB', 7],
            'returnUrl'       => 'https://neika.test/finish',
            'verifiedDomains' => ['shop.test'],
        ]));

        $errorCode = $this->captureErrorCode(new CapturingHttpClient($response));

        self::assertSame(PairingException::INVALID_RESPONSE, $errorCode);
    }

    #[DataProvider('provideInvalidVerifiedDomains')]
    public function testASuccessWithInvalidVerifiedDomainsIsAnInvalidResponse(mixed $verifiedDomains): void
    {
        $payload = [
            'siteKey'      => 'pk_site',
            'baseUrl'      => 'https://shop.test',
            'channelCodes' => ['FASHION_WEB'],
            'returnUrl'    => 'https://neika.test/finish',
        ];
        if ($verifiedDomains !== null) {
            $payload['verifiedDomains'] = $verifiedDomains;
        }
        $response = new Response(200, [], json_encode($payload));

        $errorCode = $this->captureErrorCode(new CapturingHttpClient($response));

        self::assertSame(PairingException::INVALID_RESPONSE, $errorCode);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideInvalidVerifiedDomains(): iterable
    {
        yield 'missing' => [null];
        yield 'not a list' => [['main' => 'shop.test']];
        yield 'a string' => ['shop.test'];
        yield 'a non-string item' => [['shop.test', 7]];
        yield 'an empty item' => [['shop.test', '']];
    }

    private function captureErrorCode(CapturingHttpClient $httpClient): string
    {
        try {
            $this->createClient($httpClient)->complete('raw-code', 'api-secret', 'ingest-secret');
        } catch (PairingException $exception) {
            return $exception->getErrorCode();
        }

        self::fail('Expected a PairingException.');
    }

    private function createClient(CapturingHttpClient $httpClient): PairingClient
    {
        $factory = new Psr17Factory();

        return new PairingClient($httpClient, $factory, $factory, 'https://neika.test/');
    }
}

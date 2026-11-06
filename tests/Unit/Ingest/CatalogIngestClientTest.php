<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\Ingest;

use Neika\Sdk\DTO\CatalogChange;
use Neika\Sdk\DTO\SiteCredentials;
use Neika\Sdk\Enum\CatalogJobStatus;
use Neika\Sdk\Enum\CatalogSourceName;
use Neika\Sdk\Exception\CatalogIngestException;
use Neika\Sdk\Ingest\CatalogIngestClient;
use Neika\Sdk\Tests\Unit\Fixtures\CapturingHttpClient;
use Neika\Sdk\Tests\Unit\Fixtures\TransportFailure;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

class CatalogIngestClientTest extends TestCase
{
    public function testAnAcceptedBatchIsPostedWithTheSiteKeyBearerAndParsedIntoJobs(): void
    {
        $response = new Response(202, [], json_encode([
            'jobs' => [
                ['externalId' => 'CLIPPER-01', 'jobId' => 'document/018f-uuid/products-cs_CZ-CLIPPER-01', 'status' => 'queued', 'violation' => null],
                ['externalId' => 'BAD-99', 'jobId' => null, 'status' => 'rejected', 'violation' => 'unknown id'],
            ],
        ]));
        $httpClient = new CapturingHttpClient($response);
        $client = $this->createClient($httpClient);

        $change = new CatalogChange(CatalogSourceName::Products, 'cs_CZ', ['CLIPPER-01', 'BAD-99']);
        $result = $client->send($this->createCredentials(),$change);

        self::assertTrue($result->accepted);
        self::assertFalse($result->isThrottled());
        self::assertCount(2, $result->jobs);
        self::assertSame(CatalogJobStatus::Queued, $result->jobs[0]->status);
        self::assertSame('document/018f-uuid/products-cs_CZ-CLIPPER-01', $result->jobs[0]->jobId);
        self::assertTrue($result->jobs[1]->isRejected());
        self::assertNull($result->jobs[1]->jobId);
        self::assertSame('unknown id', $result->jobs[1]->violation);

        $request = $httpClient->lastRequest;
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://neika.test/api/v1/catalog/changes', (string) $request->getUri());
        self::assertSame('Bearer pk_site.ingest-secret', $request->getHeaderLine('Authorization'));
        self::assertSame(
            ['source' => 'products', 'locale' => 'cs_CZ', 'externalIds' => ['CLIPPER-01', 'BAD-99']],
            json_decode((string) $request->getBody(), true),
        );
    }

    public function testA429IsReportedAsThrottledWithTheRetryAfterHeader(): void
    {
        $response = new Response(429, ['Retry-After' => '300'], json_encode(['error' => ['code' => 'ingest_backlog']]));
        $client = $this->createClient(new CapturingHttpClient($response));

        $result = $client->send($this->createCredentials(),$this->createChange());

        self::assertFalse($result->accepted);
        self::assertTrue($result->isThrottled());
        self::assertSame(300, $result->retryAfterSeconds);
        self::assertSame([], $result->jobs);
    }

    public function testAThrottleWithoutANumericRetryAfterWaitsOneSecond(): void
    {
        $response = new Response(429, [], json_encode(['error' => ['code' => 'rate_limited']]));
        $client = $this->createClient(new CapturingHttpClient($response));

        $result = $client->send($this->createCredentials(),$this->createChange());

        self::assertTrue($result->isThrottled());
        self::assertSame(1, $result->retryAfterSeconds);
    }

    public function testA503IsReportedAsThrottledSoTheSameIdsAreSentAgain(): void
    {
        $response = new Response(503, ['Retry-After' => '60'], json_encode(['error' => ['code' => 'internal']]));
        $client = $this->createClient(new CapturingHttpClient($response));

        $result = $client->send($this->createCredentials(),$this->createChange());

        self::assertFalse($result->accepted);
        self::assertTrue($result->isThrottled());
        self::assertSame(60, $result->retryAfterSeconds);
    }

    public function testAnAuthFailureThrowsWithTheStatusAndBackendCode(): void
    {
        $response = new Response(401, [], json_encode(['error' => ['code' => 'invalid_token']]));
        $client = $this->createClient(new CapturingHttpClient($response));

        try {
            $client->send($this->createCredentials(),$this->createChange());
            self::fail('Expected a CatalogIngestException.');
        } catch (CatalogIngestException $exception) {
            self::assertSame(401, $exception->getStatusCode());
            self::assertSame('invalid_token', $exception->getBackendErrorCode());
        }
    }

    public function testATransportFailureIsWrapped(): void
    {
        $httpClient = new CapturingHttpClient(new Response(202), new TransportFailure('connection refused'));
        $client = $this->createClient($httpClient);

        $this->expectException(CatalogIngestException::class);

        $client->send($this->createCredentials(),$this->createChange());
    }

    public function testAnEmptyBatchIsRejectedBeforeSending(): void
    {
        $httpClient = new CapturingHttpClient(new Response(202));
        $client = $this->createClient($httpClient);

        try {
            $client->send($this->createCredentials(),new CatalogChange(CatalogSourceName::Products, 'cs_CZ', []));
            self::fail('Expected an InvalidArgumentException.');
        } catch (\InvalidArgumentException) {
            self::assertNull($httpClient->lastRequest);
        }
    }

    public function testTheMaximumBatchOfFiveHundredIsAccepted(): void
    {
        $httpClient = new CapturingHttpClient(new Response(202));
        $client = $this->createClient($httpClient);
        $externalIds = $this->createExternalIds(500);

        $result = $client->send($this->createCredentials(),new CatalogChange(CatalogSourceName::Products, 'cs_CZ', $externalIds));

        self::assertTrue($result->accepted);
        self::assertNotNull($httpClient->lastRequest);
    }

    public function testABatchOverTheLimitIsRejectedBeforeSending(): void
    {
        $httpClient = new CapturingHttpClient(new Response(202));
        $client = $this->createClient($httpClient);
        $externalIds = $this->createExternalIds(501);

        try {
            $client->send($this->createCredentials(),new CatalogChange(CatalogSourceName::Products, 'cs_CZ', $externalIds));
            self::fail('Expected an InvalidArgumentException.');
        } catch (\InvalidArgumentException) {
            self::assertNull($httpClient->lastRequest);
        }
    }

    public function testAnUnrecognisedStatusMapsToUnknownRatherThanQueued(): void
    {
        $response = new Response(202, [], json_encode([
            'jobs' => [['externalId' => 'X-1', 'jobId' => 'j1', 'status' => 'processing', 'violation' => null]],
        ]));
        $client = $this->createClient(new CapturingHttpClient($response));

        $result = $client->send($this->createCredentials(),$this->createChange());

        self::assertSame(CatalogJobStatus::Unknown, $result->jobs[0]->status);
    }

    /**
     * @return list<string>
     */
    private function createExternalIds(int $count): array
    {
        return array_map(static fn (int $index): string => 'ID-' . $index, range(1, $count));
    }

    private function createClient(CapturingHttpClient $httpClient): CatalogIngestClient
    {
        $factory = new Psr17Factory();

        return new CatalogIngestClient($httpClient, $factory, $factory, 'https://neika.test/');
    }

    private function createCredentials(): SiteCredentials
    {
        return new SiteCredentials('pk_site', 'ingest-secret');
    }

    private function createChange(): CatalogChange
    {
        return new CatalogChange(CatalogSourceName::Products, 'cs_CZ', ['CLIPPER-01']);
    }
}

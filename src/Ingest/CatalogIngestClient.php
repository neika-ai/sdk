<?php

declare(strict_types=1);

namespace Neika\Sdk\Ingest;

use Neika\Sdk\DTO\CatalogChange;
use Neika\Sdk\DTO\CatalogChangeJob;
use Neika\Sdk\DTO\CatalogChangeResult;
use Neika\Sdk\DTO\SiteCredentials;
use Neika\Sdk\Enum\CatalogJobStatus;
use Neika\Sdk\Exception\CatalogIngestException;
use Neika\Sdk\Http\BackendRejectionMessage;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

class CatalogIngestClient
{
    public function __construct(
        private readonly ClientInterface         $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface  $streamFactory,
        private readonly string                  $backendUrl,
    ) {
    }

    public function getMaxExternalIdsPerRequest(): int
    {
        return 500;
    }

    public function send(SiteCredentials $credentials, CatalogChange $change): CatalogChangeResult
    {
        $this->guardBatchSize($change);

        $request = $this->buildRequest($credentials, $change);

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new CatalogIngestException('The catalog ingest request could not be sent.', 0, null, $exception);
        }

        return $this->parseResponse($response);
    }

    private function guardBatchSize(CatalogChange $change): void
    {
        $count = count($change->externalIds);
        $isWithinRange = $count >= 1 && $count <= $this->getMaxExternalIdsPerRequest();
        if ($isWithinRange) {
            return;
        }

        throw new \InvalidArgumentException(sprintf(
            'A catalog change has %d external ids. Send 1 to %d per call.',
            $count,
            $this->getMaxExternalIdsPerRequest(),
        ));
    }

    private function buildRequest(SiteCredentials $credentials, CatalogChange $change): RequestInterface
    {
        $body = json_encode($change->jsonSerialize(), JSON_THROW_ON_ERROR);
        $stream = $this->streamFactory->createStream($body);

        return $this->requestFactory->createRequest('POST', $this->buildChangesUrl())
            ->withHeader('Authorization', 'Bearer ' . $credentials->getBearerToken())
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($stream);
    }

    private function buildChangesUrl(): string
    {
        return rtrim($this->backendUrl, '/') . '/api/v1/catalog/changes';
    }

    private function parseResponse(ResponseInterface $response): CatalogChangeResult
    {
        $statusCode = $response->getStatusCode();

        $isAccepted = $statusCode === 202;
        if ($isAccepted) {
            return new CatalogChangeResult(true, $this->parseJobs($response));
        }

        $isThrottled = $statusCode === 429;
        $isUnavailable = $statusCode === 503;
        if ($isThrottled || $isUnavailable) {
            return new CatalogChangeResult(false, [], $this->readRetryAfterSeconds($response));
        }

        throw $this->buildError($response, $statusCode);
    }

    /**
     * @return list<CatalogChangeJob>
     */
    private function parseJobs(ResponseInterface $response): array
    {
        $payload = $this->decodeBody($response);
        $rawJobs = $payload['jobs'] ?? [];
        if (!is_array($rawJobs)) {
            return [];
        }

        $jobs = [];
        foreach ($rawJobs as $rawJob) {
            $jobs[] = $this->parseJob($rawJob);
        }

        return $jobs;
    }

    private function parseJob(mixed $rawJob): CatalogChangeJob
    {
        $rawJob = is_array($rawJob) ? $rawJob : [];

        $externalId = isset($rawJob['externalId']) ? (string) $rawJob['externalId'] : '';

        $rawJobId = $rawJob['jobId'] ?? null;
        $jobId = $rawJobId === null ? null : (string) $rawJobId;

        $rawStatus = isset($rawJob['status']) ? (string) $rawJob['status'] : '';
        $status = CatalogJobStatus::tryFrom($rawStatus) ?? CatalogJobStatus::Unknown;

        $rawViolation = $rawJob['violation'] ?? null;
        $violation = $rawViolation === null ? null : (string) $rawViolation;

        return new CatalogChangeJob($externalId, $jobId, $status, $violation);
    }

    private function readRetryAfterSeconds(ResponseInterface $response): int
    {
        $header = $response->getHeaderLine('Retry-After');
        $isSeconds = ctype_digit($header);
        if (!$isSeconds) {
            return $this->getDefaultRetryAfterSeconds();
        }

        return (int) $header;
    }

    private function getDefaultRetryAfterSeconds(): int
    {
        return 1;
    }

    private function buildError(ResponseInterface $response, int $statusCode): CatalogIngestException
    {
        $payload = $this->decodeBody($response);
        $error = $payload['error'] ?? [];
        $error = is_array($error) ? $error : [];

        $backendErrorCode = isset($error['code']) ? (string) $error['code'] : null;
        $message = (new BackendRejectionMessage())->build('catalog ingest', $statusCode, $error);

        return new CatalogIngestException($message, $statusCode, $backendErrorCode);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(ResponseInterface $response): array
    {
        $raw = (string) $response->getBody();
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}

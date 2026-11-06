<?php

declare(strict_types=1);

namespace Neika\Sdk\Telemetry;

use Neika\Sdk\DTO\ChatOrder;
use Neika\Sdk\DTO\SiteCredentials;
use Neika\Sdk\Exception\TelemetryException;
use Neika\Sdk\Http\BackendRejectionMessage;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

class TelemetryClient
{
    public function __construct(
        private readonly ClientInterface         $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface  $streamFactory,
        private readonly string                  $backendUrl,
    ) {
    }

    public function reportLinkVisit(SiteCredentials $credentials, string $clickId, string $pageUrl): void
    {
        $payload = ['clickId' => $clickId, 'pageUrl' => $pageUrl];

        $this->post($credentials, '/api/v1/catalog/link-visits', $payload);
    }

    public function reportOrder(SiteCredentials $credentials, ChatOrder $order): void
    {
        $this->post($credentials, '/api/v1/catalog/chat-orders', $order->jsonSerialize());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(SiteCredentials $credentials, string $path, array $payload): void
    {
        $request = $this->buildRequest($credentials, $path, $payload);

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new TelemetryException('The telemetry request could not be sent.', 0, null, $exception);
        }

        $statusCode = $response->getStatusCode();
        $isAccepted = $statusCode === 202;
        if ($isAccepted) {
            return;
        }

        throw $this->buildError($response, $statusCode);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildRequest(SiteCredentials $credentials, string $path, array $payload): RequestInterface
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $stream = $this->streamFactory->createStream($body);
        $url = rtrim($this->backendUrl, '/') . $path;

        return $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Authorization', 'Bearer ' . $credentials->getBearerToken())
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($stream);
    }

    private function buildError(ResponseInterface $response, int $statusCode): TelemetryException
    {
        $payload = $this->decodeBody($response);
        $error = $payload['error'] ?? [];
        $error = is_array($error) ? $error : [];

        $backendErrorCode = isset($error['code']) ? (string) $error['code'] : null;
        $message = (new BackendRejectionMessage())->build('telemetry', $statusCode, $error);

        return new TelemetryException($message, $statusCode, $backendErrorCode);
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

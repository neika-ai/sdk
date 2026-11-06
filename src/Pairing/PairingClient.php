<?php

declare(strict_types=1);

namespace Neika\Sdk\Pairing;

use Neika\Sdk\Exception\PairingException;
use Neika\Sdk\Http\BackendRejectionMessage;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

class PairingClient
{
    public function __construct(
        private readonly ClientInterface         $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface  $streamFactory,
        private readonly string                  $backendUrl,
    ) {
    }

    public function complete(
        #[\SensitiveParameter] string $code,
        #[\SensitiveParameter] string $apiSecret,
        #[\SensitiveParameter] string $ingestSecret,
    ): PairingResult {
        $request = $this->buildRequest($code, $apiSecret, $ingestSecret);

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new PairingException(
                'The pairing request could not be sent.',
                PairingException::TRANSPORT_FAILED,
                $exception,
            );
        }

        return $this->parseResponse($response);
    }

    private function buildRequest(
        #[\SensitiveParameter] string $code,
        #[\SensitiveParameter] string $apiSecret,
        #[\SensitiveParameter] string $ingestSecret,
    ): RequestInterface {
        $payload = ['code' => $code, 'apiSecret' => $apiSecret, 'ingestSecret' => $ingestSecret];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $stream = $this->streamFactory->createStream($body);

        return $this->requestFactory->createRequest('POST', $this->buildCompleteUrl())
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($stream);
    }

    private function buildCompleteUrl(): string
    {
        return rtrim($this->backendUrl, '/') . '/api/v1/tool-server-pairings/complete';
    }

    private function parseResponse(ResponseInterface $response): PairingResult
    {
        $statusCode = $response->getStatusCode();
        $payload = $this->decodeBody($response);

        $isSuccess = $statusCode === 200;
        if (!$isSuccess) {
            throw $this->buildError($payload, $statusCode);
        }

        return $this->parseResult($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function parseResult(array $payload): PairingResult
    {
        $siteKey = $payload['siteKey'] ?? null;
        $baseUrl = $payload['baseUrl'] ?? null;
        $returnUrl = $payload['returnUrl'] ?? null;
        $channelCodes = $this->parseStringList($payload['channelCodes'] ?? null);
        $verifiedDomains = $this->parseVerifiedDomains($payload['verifiedDomains'] ?? null);

        $hasSiteKey = is_string($siteKey) && $siteKey !== '';
        $hasBaseUrl = is_string($baseUrl) && $baseUrl !== '';
        $hasReturnUrl = is_string($returnUrl) && $returnUrl !== '';
        $hasChannelCodes = $channelCodes !== null;
        $hasVerifiedDomains = $verifiedDomains !== null;
        $isComplete = $hasSiteKey && $hasBaseUrl && $hasReturnUrl && $hasChannelCodes && $hasVerifiedDomains;
        if (!$isComplete) {
            throw new PairingException(
                'The pairing response is missing a field or has one of the wrong type.',
                PairingException::INVALID_RESPONSE,
            );
        }

        return new PairingResult($siteKey, $baseUrl, $channelCodes, $returnUrl, $verifiedDomains);
    }

    /**
     * @return list<string>|null
     */
    private function parseVerifiedDomains(mixed $rawVerifiedDomains): ?array
    {
        $verifiedDomains = $this->parseStringList($rawVerifiedDomains);
        if ($verifiedDomains === null) {
            return null;
        }

        $hasEmptyDomain = in_array('', $verifiedDomains, true);
        if ($hasEmptyDomain) {
            return null;
        }

        return $verifiedDomains;
    }

    /**
     * @return list<string>|null
     */
    private function parseStringList(mixed $rawList): ?array
    {
        $isList = is_array($rawList) && array_is_list($rawList);
        if (!$isList) {
            return null;
        }

        foreach ($rawList as $item) {
            $isString = is_string($item);
            if (!$isString) {
                return null;
            }
        }

        return $rawList;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildError(array $payload, int $statusCode): PairingException
    {
        $error = $payload['error'] ?? [];
        $error = is_array($error) ? $error : [];

        $backendErrorCode = $error['code'] ?? null;
        $hasBackendErrorCode = is_string($backendErrorCode) && $backendErrorCode !== '';
        $errorCode = $hasBackendErrorCode ? $backendErrorCode : PairingException::INVALID_RESPONSE;
        $message = (new BackendRejectionMessage())->build('pairing', $statusCode, $error);

        return new PairingException($message, $errorCode);
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

<?php

declare(strict_types=1);

namespace Neika\Sdk\Tests\Unit\Fixtures;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class CapturingHttpClient implements ClientInterface
{
    public ?RequestInterface $lastRequest = null;

    public function __construct(
        private readonly ResponseInterface $response,
        private readonly ?ClientExceptionInterface $failure = null,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->response;
    }
}

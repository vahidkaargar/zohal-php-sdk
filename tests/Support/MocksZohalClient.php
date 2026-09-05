<?php

declare(strict_types=1);

namespace Zohal\Sdk\Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Zohal\Sdk\ZohalClient;

/**
 * Shared helper for building a ZohalClient backed by Guzzle's MockHandler,
 * so tests never hit the real network. `$history`, if passed, is filled
 * with one ['request' => ..., 'response' => ...] entry per call, in
 * order, so tests can assert on the exact outgoing request (method, URI,
 * body, headers).
 */
trait MocksZohalClient
{
    /**
     * @param array<int, Response> $responses queued in call order
     * @param array<int, array{request: RequestInterface, response: mixed}>|null $history
     */
    protected function makeMockClient(array $responses, ?array &$history = null, string $token = 'test-token'): ZohalClient
    {
        $history = [];

        $handlerStack = HandlerStack::create(new MockHandler($responses));
        $handlerStack->push(Middleware::history($history));

        $httpClient = new Client([
            'handler' => $handlerStack,
            'base_uri' => 'https://service.zohal.io/api/v0/',
        ]);

        return new ZohalClient($token, 'https://service.zohal.io/api/v0', $httpClient);
    }

    protected function jsonResponse(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Builds a standard {response_body:{data,error_code,message},result}
     * success envelope around $data.
     */
    protected function envelope(array $data, string $message = 'موفق'): array
    {
        return [
            'response_body' => [
                'data' => $data,
                'error_code' => null,
                'message' => $message,
            ],
            'result' => 1,
        ];
    }

    /**
     * Builds a business-error envelope (2xx status, error_code set).
     */
    protected function errorEnvelope(string $errorCode, string $message): array
    {
        return [
            'response_body' => [
                'data' => [],
                'error_code' => $errorCode,
                'message' => $message,
            ],
            'result' => 1,
        ];
    }
}

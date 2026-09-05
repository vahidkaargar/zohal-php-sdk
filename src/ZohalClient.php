<?php

declare(strict_types=1);

namespace Zohal\Sdk;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Zohal\Sdk\Exceptions\ZohalBusinessException;
use Zohal\Sdk\Exceptions\ZohalException;
use Zohal\Sdk\Exceptions\ZohalRequestException;

/**
 * Thin HTTP wrapper over the Zohal API. Most endpoints return the same
 * envelope: {"response_body": {"data": {...}, "error_code": ?string,
 * "message": string}, "result": int} — post()/get() unwrap that and
 * return `data`. A handful of endpoints (some biometric and
 * credit_inquiry ones) don't follow this envelope at all: fields sit
 * directly under response_body with no `data` key, or — for
 * credit_inquiry/send_otp — with no response_body wrapper whatsoever.
 * postRaw()/getRaw() return the full decoded body for those; the caller
 * picks out whatever shape the spec actually documents for that one
 * endpoint.
 *
 * Every method turns network failure, a non-2xx status, or a 2xx with a
 * business error_code into a typed exception, so service classes only
 * ever see a clean array or a ZohalException.
 */
final class ZohalClient
{
    private const DEFAULT_BASE_URI = 'https://service.zohal.io/api/v0';

    private readonly ClientInterface $httpClient;

    public function __construct(
        private readonly string $token,
        string $baseUri = self::DEFAULT_BASE_URI,
        ?ClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => rtrim($baseUri, '/') . '/',
            'timeout' => 15.0,
        ]);
    }

    /**
     * POST $payload as JSON to $path and return the unwrapped `data` array.
     *
     * @throws ZohalRequestException  on network failure or non-2xx status
     * @throws ZohalBusinessException on a 2xx response with error_code set
     */
    public function post(string $path, array $payload = []): array
    {
        $body = $this->send('POST', $path, ['json' => $payload]);

        return $body['response_body']['data'] ?? [];
    }

    /**
     * POST a multipart/form-data body (e.g. file uploads) and return the
     * unwrapped `data` array. $parts is Guzzle's multipart array shape: a
     * list of ['name' => ..., 'contents' => ...] entries.
     *
     * @param array<int, array{name: string, contents: mixed}> $parts
     *
     * @throws ZohalRequestException  on network failure or non-2xx status
     * @throws ZohalBusinessException on a 2xx response with error_code set
     */
    public function postMultipart(string $path, array $parts): array
    {
        $body = $this->send('POST', $path, ['multipart' => $parts]);

        return $body['response_body']['data'] ?? [];
    }

    /**
     * GET $path and return the unwrapped `data` array.
     *
     * @throws ZohalRequestException  on network failure or non-2xx status
     * @throws ZohalBusinessException on a 2xx response with error_code set
     */
    public function get(string $path): array
    {
        $body = $this->send('GET', $path, []);

        return $body['response_body']['data'] ?? [];
    }

    /**
     * Like post(), but returns the full decoded response body instead of
     * unwrapping response_body.data — for endpoints that don't use the
     * usual envelope.
     *
     * @throws ZohalRequestException
     * @throws ZohalBusinessException
     */
    public function postRaw(string $path, array $payload = []): array
    {
        return $this->send('POST', $path, ['json' => $payload]);
    }

    /**
     * @see postRaw()
     *
     * @throws ZohalRequestException
     * @throws ZohalBusinessException
     */
    public function getRaw(string $path): array
    {
        return $this->send('GET', $path, []);
    }

    /**
     * Like postMultipart(), but returns the full decoded response body
     * instead of unwrapping response_body.data.
     *
     * @param array<int, array{name: string, contents: mixed}> $parts
     *
     * @throws ZohalRequestException
     * @throws ZohalBusinessException
     */
    public function postMultipartRaw(string $path, array $parts): array
    {
        return $this->send('POST', $path, ['multipart' => $parts]);
    }

    /**
     * @throws ZohalRequestException
     * @throws ZohalBusinessException
     */
    private function send(string $method, string $path, array $options): array
    {
        try {
            $response = $this->httpClient->request($method, ltrim($path, '/'), $options + [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Accept' => 'application/json',
                ],
                'http_errors' => false,
            ]);
        } catch (GuzzleException $exception) {
            throw new ZohalRequestException(
                "Zohal request to {$path} failed: {$exception->getMessage()}",
                context: ['path' => $path],
            );
        }

        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);

        if ($status < 200 || $status >= 300) {
            $message = is_array($body)
                ? ($body['response_body']['message'] ?? "Zohal request to {$path} failed with HTTP {$status}.")
                : "Zohal request to {$path} failed with HTTP {$status}.";

            throw new ZohalRequestException(
                $message,
                errorCode: is_array($body) ? ($body['response_body']['error_code'] ?? null) : null,
                httpStatus: $status,
                context: ['path' => $path, 'body' => $body],
            );
        }

        if (!is_array($body)) {
            // A couple of endpoints (e.g. credit_inquiry/verify_otp)
            // document a 2xx response with no schema at all. A 2xx is
            // still success, so treat an empty/unparseable body as an
            // empty payload rather than raising an error.
            return [];
        }

        $errorCode = $body['response_body']['error_code'] ?? null;

        if ($errorCode !== null) {
            throw new ZohalBusinessException(
                $body['response_body']['message'] ?? $errorCode,
                errorCode: $errorCode,
                httpStatus: $status,
                context: ['path' => $path, 'body' => $body],
            );
        }

        return $body;
    }
}

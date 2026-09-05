<?php

declare(strict_types=1);

namespace Zohal\Sdk\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Zohal\Sdk\Exceptions\ZohalBusinessException;
use Zohal\Sdk\Exceptions\ZohalRequestException;
use Zohal\Sdk\Tests\Support\MocksZohalClient;
use Zohal\Sdk\ZohalClient;

final class ZohalClientTest extends TestCase
{
    use MocksZohalClient;

    public function testPostUnwrapsDataOnSuccess(): void
    {
        $client = $this->makeMockClient([
            $this->jsonResponse(200, $this->envelope(['name' => 'نام صاحب کارت'])),
        ], $history);

        $data = $client->post('services/inquiry/card_inquiry', ['card_number' => '6362XXXXXXX11']);

        self::assertSame(['name' => 'نام صاحب کارت'], $data);
        self::assertCount(1, $history);

        $request = $history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://service.zohal.io/api/v0/services/inquiry/card_inquiry', (string) $request->getUri());
        self::assertSame('Bearer test-token', $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame(
            ['card_number' => '6362XXXXXXX11'],
            json_decode((string) $request->getBody(), true),
        );
    }

    public function testPostThrowsBusinessExceptionOnErrorCode(): void
    {
        $client = $this->makeMockClient([
            $this->jsonResponse(200, $this->errorEnvelope('CARD_NOT_FOUND', 'کارت وارد شده در سيستم بانکي وجود ندارد')),
        ]);

        try {
            $client->post('services/inquiry/card_inquiry', ['card_number' => '0']);
            self::fail('Expected ZohalBusinessException');
        } catch (ZohalBusinessException $e) {
            self::assertSame('CARD_NOT_FOUND', $e->getErrorCode());
            self::assertSame('کارت وارد شده در سيستم بانکي وجود ندارد', $e->getMessage());
            self::assertSame(200, $e->getHttpStatus());
        }
    }

    /**
     * @dataProvider httpErrorStatusProvider
     */
    public function testPostThrowsRequestExceptionOnNonSuccessStatus(int $status, string $bodyErrorCode): void
    {
        $client = $this->makeMockClient([
            $this->jsonResponse($status, [
                'response_body' => ['error_code' => $bodyErrorCode, 'message' => 'سرویس مورد نظر در حال حاضر در دسترس نیست'],
            ]),
        ]);

        try {
            $client->post('services/inquiry/iban', ['iban' => 'IR00']);
            self::fail('Expected ZohalRequestException');
        } catch (ZohalRequestException $e) {
            self::assertSame($status, $e->getHttpStatus());
            self::assertSame($bodyErrorCode, $e->getErrorCode());
        }
    }

    public static function httpErrorStatusProvider(): array
    {
        return [
            '404 not found' => [404, 'SERVICE_NOT_FOUND'],
            '500 internal error' => [500, 'INTERNAL_ERROR'],
            '503 unavailable' => [503, 'SERVICE_UNAVAILABLE'],
        ];
    }

    public function testPostThrowsRequestExceptionOnNetworkFailure(): void
    {
        $handlerStack = \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler([
            new ConnectException('Connection refused', new Request('POST', 'services/inquiry/iban')),
        ]));
        $httpClient = new \GuzzleHttp\Client(['handler' => $handlerStack, 'base_uri' => 'https://service.zohal.io/api/v0/']);
        $client = new ZohalClient('test-token', 'https://service.zohal.io/api/v0', $httpClient);

        $this->expectException(ZohalRequestException::class);
        $client->post('services/inquiry/iban', ['iban' => 'IR00']);
    }

    public function testGetUsesGetMethodAndUnwrapsData(): void
    {
        $client = $this->makeMockClient([
            $this->jsonResponse(200, $this->envelope(['reference_id' => 'ref-1', 'status' => 'completed'])),
        ], $history);

        $data = $client->get('services/inquiry/credit_inquiry/result/ref-1');

        self::assertSame(['reference_id' => 'ref-1', 'status' => 'completed'], $data);
        self::assertSame('GET', $history[0]['request']->getMethod());
        self::assertSame(
            'https://service.zohal.io/api/v0/services/inquiry/credit_inquiry/result/ref-1',
            (string) $history[0]['request']->getUri(),
        );
    }

    public function testPostMultipartSendsMultipartBodyAndUnwrapsData(): void
    {
        $client = $this->makeMockClient([
            $this->jsonResponse(200, $this->envelope(['front' => ['first_name' => 'حمید']])),
        ], $history);

        $data = $client->postMultipart('services/inquiry/national_card_ocr', [
            ['name' => 'national_card_front', 'contents' => 'fake-bytes', 'filename' => 'front.jpg'],
        ]);

        self::assertSame(['front' => ['first_name' => 'حمید']], $data);

        $request = $history[0]['request'];
        self::assertStringStartsWith('multipart/form-data', $request->getHeaderLine('Content-Type'));
        self::assertStringContainsString('name="national_card_front"', (string) $request->getBody());
        self::assertStringContainsString('fake-bytes', (string) $request->getBody());
    }

    public function testPostRawReturnsFullBodyWithoutUnwrapping(): void
    {
        // credit_inquiry/send_otp's real response has no response_body
        // wrapper at all — postRaw must hand back exactly what came back,
        // not try to dig into response_body.data.
        $client = $this->makeMockClient([
            $this->jsonResponse(200, ['reference_id' => 'ref-1', 'status' => 'pending']),
        ]);

        $body = $client->postRaw('services/inquiry/credit_inquiry/send_otp', ['mobile' => '0912']);

        self::assertSame(['reference_id' => 'ref-1', 'status' => 'pending'], $body);
    }

    public function testPostRawStillThrowsBusinessExceptionWhenErrorCodePresent(): void
    {
        $client = $this->makeMockClient([
            $this->jsonResponse(200, $this->errorEnvelope('SOME_ERROR', 'failed')),
        ]);

        $this->expectException(ZohalBusinessException::class);
        $client->postRaw('services/some/endpoint', []);
    }

    public function testGetRawReturnsFullBodyWithoutUnwrapping(): void
    {
        // biometric/session/{id}/result nests fields directly under
        // response_body with no data key.
        $client = $this->makeMockClient([
            $this->jsonResponse(200, ['response_body' => ['reason' => 'ACCEPT', 'result' => 'matched'], 'result' => 1]),
        ]);

        $body = $client->getRaw('services/biometric/session/sess-1/result');

        self::assertSame(['response_body' => ['reason' => 'ACCEPT', 'result' => 'matched'], 'result' => 1], $body);
    }

    public function testPostMultipartRawSendsMultipartAndReturnsFullBody(): void
    {
        $client = $this->makeMockClient([
            $this->jsonResponse(200, ['response_body' => ['id' => 'media-1', 'type' => 'selfie_video'], 'result' => 1]),
        ], $history);

        $body = $client->postMultipartRaw('services/biometric/media/', [
            ['name' => 'file', 'contents' => 'fake-video-bytes', 'filename' => 'v.mp4'],
            ['name' => 'type', 'contents' => 'selfie_video'],
        ]);

        self::assertSame(['response_body' => ['id' => 'media-1', 'type' => 'selfie_video'], 'result' => 1], $body);
        self::assertStringStartsWith('multipart/form-data', $history[0]['request']->getHeaderLine('Content-Type'));
    }

    public function testSendTreatsA2xxWithUnparseableBodyAsEmptySuccess(): void
    {
        // credit_inquiry/verify_otp documents no response schema at all
        // for its 200 response; an empty/non-JSON body must not error.
        $client = $this->makeMockClient([
            $this->jsonResponse(200, [])->withBody(\GuzzleHttp\Psr7\Utils::streamFor('')),
        ]);

        $body = $client->postRaw('services/inquiry/credit_inquiry/verify_otp', ['otp' => '55555']);

        self::assertSame([], $body);
    }

    public function testDefaultConstructionSetsBaseUri(): void
    {
        $client = new ZohalClient('test-token');

        $ref = new \ReflectionProperty($client, 'httpClient');
        $innerGuzzleClient = $ref->getValue($client);

        self::assertSame('https://service.zohal.io/api/v0/', (string) $innerGuzzleClient->getConfig('base_uri'));
    }

    public function testCustomBaseUriIsNormalizedWithTrailingSlash(): void
    {
        $client = new ZohalClient('test-token', 'https://sandbox.zohal.io/api/v0');

        $ref = new \ReflectionProperty($client, 'httpClient');
        $innerGuzzleClient = $ref->getValue($client);

        self::assertSame('https://sandbox.zohal.io/api/v0/', (string) $innerGuzzleClient->getConfig('base_uri'));
    }
}

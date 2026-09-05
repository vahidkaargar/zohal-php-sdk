<?php

declare(strict_types=1);

namespace Zohal\Sdk\Tests\Services;

use PHPUnit\Framework\TestCase;
use Zohal\Sdk\Exceptions\ZohalBusinessException;
use Zohal\Sdk\Services\BiometricService;
use Zohal\Sdk\Tests\Support\MocksZohalClient;

final class BiometricServiceTest extends TestCase
{
    use MocksZohalClient;

    /** @var array<int, string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_string($path) && file_exists($path)) {
                unlink($path);
            }
        }
        $this->tempFiles = [];
    }

    private function makeTempVideoFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zohal_video_');
        self::assertIsString($path);
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    // -----------------------------------------------------------------
    // uploadMedia()
    // -----------------------------------------------------------------

    public function testUploadMediaSendsFileAndDefaultTypeAsMultipartAndReturnsUnwrappedIdAndType(): void
    {
        $videoPath = $this->makeTempVideoFile('fake-video-bytes-for-test');

        $service = new BiometricService($this->makeMockClient([
            $this->jsonResponse(200, [
                'response_body' => ['id' => 'media-123', 'type' => 'selfie_video'],
                'result' => 1,
            ]),
        ], $history));

        $result = $service->uploadMedia($videoPath);

        self::assertSame(['id' => 'media-123', 'type' => 'selfie_video'], $result);
        self::assertCount(1, $history);

        $request = $history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            'https://service.zohal.io/api/v0/services/biometric/media/',
            (string) $request->getUri(),
        );
        self::assertSame('Bearer test-token', $request->getHeaderLine('Authorization'));
        self::assertStringStartsWith('multipart/form-data', $request->getHeaderLine('Content-Type'));

        $body = (string) $request->getBody();
        self::assertStringContainsString('name="file"', $body);
        self::assertStringContainsString('filename="' . basename($videoPath) . '"', $body);
        self::assertStringContainsString('fake-video-bytes-for-test', $body);
        self::assertStringContainsString('name="type"', $body);
        self::assertStringContainsString('selfie_video', $body);
    }

    public function testUploadMediaSendsCustomTypeFieldAndReturnsUnwrappedIdAndType(): void
    {
        $videoPath = $this->makeTempVideoFile('other-video-bytes');

        $service = new BiometricService($this->makeMockClient([
            $this->jsonResponse(200, [
                'response_body' => ['id' => 'media-456', 'type' => 'id_video'],
                'result' => 1,
            ]),
        ], $history));

        $result = $service->uploadMedia($videoPath, 'id_video');

        self::assertSame(['id' => 'media-456', 'type' => 'id_video'], $result);

        $request = $history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            'https://service.zohal.io/api/v0/services/biometric/media/',
            (string) $request->getUri(),
        );

        $body = (string) $request->getBody();
        self::assertStringContainsString('name="type"', $body);
        self::assertStringContainsString('id_video', $body);
        self::assertStringContainsString('name="file"', $body);
        self::assertStringContainsString('filename="' . basename($videoPath) . '"', $body);
    }

    public function testUploadMediaDefaultsIdAndTypeToEmptyStringWhenMissingFromResponse(): void
    {
        $videoPath = $this->makeTempVideoFile('bytes');

        $service = new BiometricService($this->makeMockClient([
            $this->jsonResponse(200, [
                'response_body' => [],
                'result' => 1,
            ]),
        ]));

        $result = $service->uploadMedia($videoPath);

        self::assertSame(['id' => '', 'type' => ''], $result);
    }

    public function testUploadMediaThrowsZohalBusinessExceptionWhenErrorCodePresent(): void
    {
        $videoPath = $this->makeTempVideoFile('bytes');

        $service = new BiometricService($this->makeMockClient([
            $this->jsonResponse(200, $this->errorEnvelope('MEDIA_UPLOAD_FAILED', 'آپلود ویدیو ناموفق بود')),
        ]));

        try {
            $service->uploadMedia($videoPath);
            self::fail('Expected ZohalBusinessException');
        } catch (ZohalBusinessException $e) {
            self::assertSame('MEDIA_UPLOAD_FAILED', $e->getErrorCode());
            self::assertSame('آپلود ویدیو ناموفق بود', $e->getMessage());
            self::assertSame(200, $e->getHttpStatus());
        }
    }

    // -----------------------------------------------------------------
    // startLivenessSession()
    // -----------------------------------------------------------------

    public function testStartLivenessSessionOmitsCallbackUrlWhenNotProvided(): void
    {
        $service = new BiometricService($this->makeMockClient([
            $this->jsonResponse(200, [
                'response_body' => ['session_id' => 'sess-1', 'status' => 'pending'],
                'result' => 1,
            ]),
        ], $history));

        $result = $service->startLivenessSession('media-123', '0012345678', 'ABC123', '1370-01-01');

        self::assertSame(['session_id' => 'sess-1', 'status' => 'pending'], $result);

        $request = $history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            'https://service.zohal.io/api/v0/services/biometric/session/liveness/',
            (string) $request->getUri(),
        );

        $payload = json_decode((string) $request->getBody(), true);
        self::assertSame([
            'media' => ['selfie_video' => 'media-123'],
            'national_code' => '0012345678',
            'national_card_serial' => 'ABC123',
            'birth_date' => '1370-01-01',
        ], $payload);
        self::assertArrayNotHasKey('callback_url', $payload);
    }

    public function testStartLivenessSessionIncludesCallbackUrlWhenProvided(): void
    {
        $service = new BiometricService($this->makeMockClient([
            $this->jsonResponse(200, [
                'response_body' => ['session_id' => 'sess-2', 'status' => 'pending'],
                'result' => 1,
            ]),
        ], $history));

        $result = $service->startLivenessSession(
            'media-999',
            '0098765432',
            'XYZ789',
            '1365-05-05',
            'https://example.com/callback',
        );

        self::assertSame(['session_id' => 'sess-2', 'status' => 'pending'], $result);

        $request = $history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            'https://service.zohal.io/api/v0/services/biometric/session/liveness/',
            (string) $request->getUri(),
        );

        $payload = json_decode((string) $request->getBody(), true);
        self::assertSame([
            'media' => ['selfie_video' => 'media-999'],
            'national_code' => '0098765432',
            'national_card_serial' => 'XYZ789',
            'birth_date' => '1365-05-05',
            'callback_url' => 'https://example.com/callback',
        ], $payload);
    }

    public function testStartLivenessSessionDefaultsSessionIdAndStatusToEmptyStringWhenMissing(): void
    {
        $service = new BiometricService($this->makeMockClient([
            $this->jsonResponse(200, [
                'response_body' => [],
                'result' => 1,
            ]),
        ]));

        $result = $service->startLivenessSession('media-1', '001', 'S1', '1360-01-01');

        self::assertSame(['session_id' => '', 'status' => ''], $result);
    }

    public function testStartLivenessSessionThrowsZohalBusinessExceptionWhenErrorCodePresent(): void
    {
        $service = new BiometricService($this->makeMockClient([
            $this->jsonResponse(200, $this->errorEnvelope('INVALID_MEDIA', 'شناسه ویدیو نامعتبر است')),
        ]));

        try {
            $service->startLivenessSession('bad-media', '001', 'S1', '1360-01-01');
            self::fail('Expected ZohalBusinessException');
        } catch (ZohalBusinessException $e) {
            self::assertSame('INVALID_MEDIA', $e->getErrorCode());
            self::assertSame('شناسه ویدیو نامعتبر است', $e->getMessage());
            self::assertSame(200, $e->getHttpStatus());
        }
    }

    // -----------------------------------------------------------------
    // sessionResult()
    // -----------------------------------------------------------------

    /**
     * @dataProvider sessionResultProvider
     */
    public function testSessionResultSendsGetRequestAndReturnsUnwrappedFields(
        string $sessionId,
        string $expectedEncodedSegment,
        array $mockResponseBodyFields,
        array $expectedReturn,
    ): void {
        $service = new BiometricService($this->makeMockClient([
            $this->jsonResponse(200, [
                'response_body' => $mockResponseBodyFields,
                'result' => 1,
            ]),
        ], $history));

        $result = $service->sessionResult($sessionId);

        self::assertSame($expectedReturn, $result);
        self::assertCount(1, $history);

        $request = $history[0]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertSame(
            'https://service.zohal.io/api/v0/services/biometric/session/' . $expectedEncodedSegment . '/result',
            (string) $request->getUri(),
        );
    }

    public static function sessionResultProvider(): array
    {
        return [
            'completed session with matched result' => [
                'sess-1',
                'sess-1',
                [
                    'completed_at' => '2026-09-01T10:00:00+00:00',
                    'reason' => 'ACCEPT',
                    'result' => 'matched',
                    'status' => 'completed',
                    'type' => 'liveness',
                ],
                [
                    'completed_at' => '2026-09-01T10:00:00+00:00',
                    'reason' => 'ACCEPT',
                    'result' => 'matched',
                    'status' => 'completed',
                    'type' => 'liveness',
                ],
            ],
            'completed session with rejected face mismatch' => [
                'sess-2',
                'sess-2',
                [
                    'completed_at' => '2026-09-02T11:30:00+00:00',
                    'reason' => 'REJECT_FACE_NOT_MATCH_ID',
                    'result' => 'rejected',
                    'status' => 'completed',
                    'type' => 'liveness',
                ],
                [
                    'completed_at' => '2026-09-02T11:30:00+00:00',
                    'reason' => 'REJECT_FACE_NOT_MATCH_ID',
                    'result' => 'rejected',
                    'status' => 'completed',
                    'type' => 'liveness',
                ],
            ],
            'pending session with null completed_at and session id needing url-encoding' => [
                'sess 42/x',
                'sess%2042%2Fx',
                [
                    'status' => 'pending',
                ],
                [
                    'completed_at' => null,
                    'reason' => '',
                    'result' => '',
                    'status' => 'pending',
                    'type' => '',
                ],
            ],
        ];
    }

    public function testSessionResultReturnsExplicitNullCompletedAtUnchanged(): void
    {
        $service = new BiometricService($this->makeMockClient([
            $this->jsonResponse(200, [
                'response_body' => [
                    'completed_at' => null,
                    'reason' => '',
                    'result' => '',
                    'status' => 'pending',
                    'type' => 'liveness',
                ],
                'result' => 1,
            ]),
        ], $history));

        $result = $service->sessionResult('sess-3');

        self::assertNull($result['completed_at']);
        self::assertSame([
            'completed_at' => null,
            'reason' => '',
            'result' => '',
            'status' => 'pending',
            'type' => 'liveness',
        ], $result);

        self::assertSame(
            'https://service.zohal.io/api/v0/services/biometric/session/sess-3/result',
            (string) $history[0]['request']->getUri(),
        );
    }
}

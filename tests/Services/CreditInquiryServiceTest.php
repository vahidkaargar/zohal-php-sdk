<?php

declare(strict_types=1);

namespace Zohal\Sdk\Tests\Services;

use PHPUnit\Framework\TestCase;
use Zohal\Sdk\Exceptions\ZohalBusinessException;
use Zohal\Sdk\Services\CreditInquiryService;
use Zohal\Sdk\Tests\Support\MocksZohalClient;

final class CreditInquiryServiceTest extends TestCase
{
    use MocksZohalClient;

    // sendOtp() — POST send_otp, via postRaw(): the real response has no
    // response_body wrapper at all, so the service picks reference_id and
    // status straight off the bare top-level body.

    public function testSendOtpSendsExpectedRequestAndReturnsBareTopLevelBody(): void
    {
        $service = new CreditInquiryService($this->makeMockClient([
            $this->jsonResponse(200, ['reference_id' => 'ref-123', 'status' => 'pending']),
        ], $history));

        $result = $service->sendOtp('09121234567', '0012345678');

        self::assertSame(['reference_id' => 'ref-123', 'status' => 'pending'], $result);
        self::assertCount(1, $history);

        $request = $history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            'https://service.zohal.io/api/v0/services/inquiry/credit_inquiry/send_otp',
            (string) $request->getUri(),
        );
        self::assertSame(
            ['mobile' => '09121234567', 'national_code' => '0012345678'],
            json_decode((string) $request->getBody(), true),
        );
    }

    public function testSendOtpDefaultsMissingFieldsToEmptyString(): void
    {
        // Bare body present but missing the documented keys — sendOtp()
        // must fall back to '' rather than emit null or throw.
        $service = new CreditInquiryService($this->makeMockClient([
            $this->jsonResponse(200, []),
        ]));

        $result = $service->sendOtp('09121234567', '0012345678');

        self::assertSame(['reference_id' => '', 'status' => ''], $result);
    }

    public function testSendOtpThrowsBusinessExceptionWhenErrorCodePresent(): void
    {
        $service = new CreditInquiryService($this->makeMockClient([
            $this->jsonResponse(200, $this->errorEnvelope('INVALID_NATIONAL_CODE', 'کد ملی نامعتبر است')),
        ]));

        try {
            $service->sendOtp('09121234567', '0000000000');
            self::fail('Expected ZohalBusinessException');
        } catch (ZohalBusinessException $e) {
            self::assertSame('INVALID_NATIONAL_CODE', $e->getErrorCode());
            self::assertSame('کد ملی نامعتبر است', $e->getMessage());
            self::assertSame(200, $e->getHttpStatus());
        }
    }

    // verifyOtp() — POST verify_otp, via postRaw(): the endpoint documents
    // no response schema at all, so the service must hand back whatever
    // (if anything) postRaw() returns, untouched.

    public function testVerifyOtpSendsExpectedRequestAndPassesBodyThroughUntouched(): void
    {
        $service = new CreditInquiryService($this->makeMockClient([
            $this->jsonResponse(200, ['some_undocumented_field' => 'some_value']),
        ], $history));

        $result = $service->verifyOtp('ref-123', '55555');

        self::assertSame(['some_undocumented_field' => 'some_value'], $result);

        $request = $history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            'https://service.zohal.io/api/v0/services/inquiry/credit_inquiry/verify_otp',
            (string) $request->getUri(),
        );
        self::assertSame(
            ['reference_id' => 'ref-123', 'otp' => '55555'],
            json_decode((string) $request->getBody(), true),
        );
    }

    public function testVerifyOtpReturnsEmptyArrayOnEmptyResponseBodyWithoutThrowing(): void
    {
        // credit_inquiry/verify_otp documents no response schema for its
        // 200 response; an empty body is still success and must not error.
        $service = new CreditInquiryService($this->makeMockClient([
            $this->jsonResponse(200, [])->withBody(\GuzzleHttp\Psr7\Utils::streamFor('')),
        ]));

        $result = $service->verifyOtp('ref-123', '55555');

        self::assertSame([], $result);
    }

    public function testVerifyOtpThrowsBusinessExceptionWhenErrorCodePresent(): void
    {
        $service = new CreditInquiryService($this->makeMockClient([
            $this->jsonResponse(200, $this->errorEnvelope('INVALID_OTP', 'کد تایید نامعتبر است')),
        ]));

        try {
            $service->verifyOtp('ref-123', '00000');
            self::fail('Expected ZohalBusinessException');
        } catch (ZohalBusinessException $e) {
            self::assertSame('INVALID_OTP', $e->getErrorCode());
            self::assertSame('کد تایید نامعتبر است', $e->getMessage());
        }
    }

    // result() — GET result/{reference_id}, via the normal get(): standard
    // enveloped response, reference_id URL-encoded into the path.

    /**
     * @dataProvider referenceIdProvider
     */
    public function testResultSendsGetRequestWithEncodedReferenceIdAndReturnsUnwrappedData(
        string $referenceId,
        string $expectedEncodedSegment,
    ): void {
        $data = [
            'completed_at' => '2026-09-01T10:00:00+03:30',
            'reference_id' => $referenceId,
            'result' => ['score' => 720, 'bounced_cheques' => []],
            'service' => 'credit_inquiry',
            'status' => 'completed',
        ];

        $service = new CreditInquiryService($this->makeMockClient([
            $this->jsonResponse(200, $this->envelope($data)),
        ], $history));

        $result = $service->result($referenceId);

        self::assertSame($data, $result);

        $request = $history[0]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertSame(
            'https://service.zohal.io/api/v0/services/inquiry/credit_inquiry/result/' . $expectedEncodedSegment,
            (string) $request->getUri(),
        );
    }

    public static function referenceIdProvider(): array
    {
        return [
            'plain reference id' => ['ref-123', 'ref-123'],
            'reference id with characters requiring URL-encoding' => ['ref/abc def', 'ref%2Fabc%20def'],
        ];
    }

    public function testResultThrowsBusinessExceptionWhenErrorCodePresent(): void
    {
        $service = new CreditInquiryService($this->makeMockClient([
            $this->jsonResponse(200, $this->errorEnvelope('INQUIRY_NOT_FOUND', 'استعلام یافت نشد')),
        ]));

        try {
            $service->result('missing-ref');
            self::fail('Expected ZohalBusinessException');
        } catch (ZohalBusinessException $e) {
            self::assertSame('INQUIRY_NOT_FOUND', $e->getErrorCode());
            self::assertSame('استعلام یافت نشد', $e->getMessage());
        }
    }
}

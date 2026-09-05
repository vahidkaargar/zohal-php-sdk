<?php

declare(strict_types=1);

namespace Zohal\Sdk\Tests\Services;

use PHPUnit\Framework\TestCase;
use Zohal\Sdk\Exceptions\ZohalBusinessException;
use Zohal\Sdk\Services\InquiryService;
use Zohal\Sdk\Tests\Support\MocksZohalClient;

final class InquiryServiceTest extends TestCase
{
    use MocksZohalClient;

    /** @var list<string> temp files created for multipart tests, removed in tearDown */
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

    /**
     * @param array<int, \GuzzleHttp\Psr7\Response> $responses
     * @param array<int, array{request: \Psr\Http\Message\RequestInterface, response: mixed}>|null $history
     */
    private function makeService(array $responses, ?array &$history = null): InquiryService
    {
        return new InquiryService($this->makeMockClient($responses, $history));
    }

    private function makeTempFile(string $basename, string $contents): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid('zohal_inquiry_test_', true) . '_' . $basename;
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    // -- All plain JSON POST endpoints (23 of the 24 public methods) -----------

    /**
     * @dataProvider jsonEndpointProvider
     */
    public function testJsonPostEndpointsSendCorrectRequestAndReturnUnwrappedData(
        string $methodName,
        array $args,
        string $expectedPath,
        array $expectedPayload,
        array $mockData,
    ): void {
        $service = $this->makeService([
            $this->jsonResponse(200, $this->envelope($mockData)),
        ], $history);

        $result = $service->{$methodName}(...$args);

        self::assertSame($mockData, $result);
        self::assertCount(1, $history);

        $request = $history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertStringEndsWith($expectedPath, (string) $request->getUri());
        self::assertSame($expectedPayload, json_decode((string) $request->getBody(), true));
    }

    public static function jsonEndpointProvider(): array
    {
        return [
            'cardInquiry' => [
                'cardInquiry',
                ['6104337812345678'],
                'services/inquiry/card_inquiry',
                ['card_number' => '6104337812345678'],
                ['name' => 'علی رضایی'],
            ],
            'cardToIban' => [
                'cardToIban',
                ['6104337812345678'],
                'services/inquiry/card_to_iban',
                ['card_number' => '6104337812345678'],
                ['IBAN' => 'IR820540102680020817909002', 'bank_name' => 'بانک ملت', 'name' => 'علی رضایی'],
            ],
            'accountToIban' => [
                'accountToIban',
                ['0102680020817909002', '012'],
                'services/inquiry/account_to_iban',
                ['bank_account' => '0102680020817909002', 'bank_code' => '012'],
                ['IBAN' => 'IR820540102680020817909002'],
            ],
            'cardToAccount' => [
                'cardToAccount',
                ['6104337812345678'],
                'services/inquiry/card_to_account',
                ['card_number' => '6104337812345678'],
                ['bank_account' => '0102680020817909002', 'bank_name' => 'بانک ملت', 'name' => 'علی رضایی'],
            ],
            'iban' => [
                'iban',
                ['IR820540102680020817909002'],
                'services/inquiry/iban',
                ['iban' => 'IR820540102680020817909002'],
                ['name' => 'علی رضایی', 'bank_name' => 'بانک ملت', 'is_transferable' => true],
            ],
            'checkCardWithName' => [
                'checkCardWithName',
                ['6104337812345678', 'علی رضایی'],
                'services/inquiry/check_card_with_name',
                ['card_number' => '6104337812345678', 'name' => 'علی رضایی'],
                ['name' => 'علی رضایی'],
            ],
            'checkIbanWithName' => [
                'checkIbanWithName',
                ['IR820540102680020817909002', 'علی رضایی'],
                'services/inquiry/check_iban_with_name',
                ['IBAN' => 'IR820540102680020817909002', 'name' => 'علی رضایی'],
                ['matched' => true],
            ],
            'checkIbanWithNationalCode' => [
                'checkIbanWithNationalCode',
                ['IR820540102680020817909002', '0012345678', '1370-01-01'],
                'services/inquiry/check_iban_with_national_code',
                ['IBAN' => 'IR820540102680020817909002', 'national_code' => '0012345678', 'birth_date' => '1370-01-01'],
                ['matched' => true],
            ],
            'checkCardWithNationalCode' => [
                'checkCardWithNationalCode',
                ['6104337812345678', '0012345678', '1370-01-01'],
                'services/inquiry/check_card_with_national_code',
                ['card_number' => '6104337812345678', 'national_code' => '0012345678', 'birth_date' => '1370-01-01'],
                ['matched' => true],
            ],
            'checkSayadInquiry' => [
                'checkSayadInquiry',
                ['12345678901234567890'],
                'services/inquiry/check_sayad_inquiry',
                ['sayad_id' => '12345678901234567890'],
                [
                    'sayad_id' => '12345678901234567890',
                    'iban' => 'IR820540102680020817909002',
                    'name' => 'علی رضایی',
                    'serial_no' => 'A123',
                    'series_no' => 'B456',
                    'check_type' => 'normal',
                    'issue_date' => '1402-01-01',
                    'branch_code' => '001',
                    'expiration_date' => null,
                    'returned_cheques' => '0',
                ],
            ],
            'checkSayadInquiryChain' => [
                'checkSayadInquiryChain',
                ['sayadi', '0012345678', '12345678901234567890'],
                'services/inquiry/check_sayad_inquiry/chain',
                ['cheque_type' => 'sayadi', 'national_code' => '0012345678', 'sayad_id' => '12345678901234567890'],
                [
                    'chain' => [
                        [
                            'role_type' => 1,
                            'customers' => [
                                ['customer_type' => 1, 'name' => 'علی رضایی', 'national_code' => '0012345678'],
                            ],
                        ],
                    ],
                ],
            ],
            'bouncedCheque' => [
                'bouncedCheque',
                ['0012345678', 1],
                'services/inquiry/bounced_cheque',
                ['national_code' => '0012345678', 'nationality_type' => 1],
                ['count' => 2],
            ],
            'shahkar' => [
                'shahkar',
                ['09121234567', '0012345678'],
                'services/inquiry/shahkar',
                ['mobile' => '09121234567', 'national_code' => '0012345678'],
                ['matched' => true],
            ],
            'nationalIdentityInquiry' => [
                'nationalIdentityInquiry',
                ['0012345678', '1370-01-01'],
                'services/inquiry/national_identity_inquiry',
                ['national_code' => '0012345678', 'birth_date' => '1370-01-01'],
                [
                    'matched' => true,
                    'first_name' => 'علی',
                    'last_name' => 'رضایی',
                    'father_name' => 'محمد',
                    'national_code' => '0012345678',
                    'alive' => true,
                    'is_dead' => false,
                ],
            ],
            'companyInquiry' => [
                'companyInquiry',
                ['10861234567'],
                'services/inquiry/company_inquiry',
                ['national_id' => '10861234567'],
                [
                    'name' => 'شرکت نمونه',
                    'national_id' => '10861234567',
                    'company_type' => 'private',
                    'register_date' => '1390-01-01',
                    'register_number' => '12345',
                    'issuance_date' => '1390-02-01',
                    'created_at' => '1390-01-01',
                    'address' => 'تهران',
                    'postal_code' => '1234567890',
                    'phone_number' => null,
                    'fax_number' => '02100000000',
                    'email_address' => 'info@example.com',
                    'activity_end_date' => '',
                ],
            ],
            'companyInquiryBoardMembers' => [
                'companyInquiryBoardMembers',
                ['10861234567'],
                'services/inquiry/company_inquiry/board_members',
                ['national_id' => '10861234567'],
                [
                    'company_title' => 'شرکت نمونه',
                    'board_members' => [
                        ['name' => 'علی رضایی', 'position' => 'مدیرعامل', 'start_date' => '1390-01-01', 'duration' => '2 سال'],
                    ],
                ],
            ],
            'companyInquiryBoardMembersHistory' => [
                'companyInquiryBoardMembersHistory',
                ['10861234567'],
                'services/inquiry/company_inquiry/board_members/history',
                ['national_id' => '10861234567'],
                [
                    'company_title' => 'شرکت نمونه',
                    'board_members' => [
                        [
                            'position' => 'مدیرعامل',
                            'name' => 'علی رضایی',
                            'start_date' => '1385-01-01',
                            'duration' => '5 سال',
                            'end_date' => '1390-01-01',
                        ],
                    ],
                ],
            ],
            'postalCodeInquiry' => [
                'postalCodeInquiry',
                ['1234567890'],
                'services/inquiry/postal_code_inquiry',
                ['postal_code' => '1234567890'],
                [
                    'address' => [
                        'province' => 'تهران',
                        'town' => 'تهران',
                        'street' => 'ولیعصر',
                        'street2' => '',
                        'number' => '10',
                        'floor' => '2',
                        'side_floor' => '',
                        'district' => '6',
                        'building_name' => '',
                        'description' => '',
                    ],
                ],
            ],
            'vehicleInquiryTotalViolations' => [
                'vehicleInquiryTotalViolations',
                ['09121234567', '0012345678', '11223344', '55'],
                'services/inquiry/vehicle_inquiry/total_violations',
                ['mobile' => '09121234567', 'national_code' => '0012345678', 'plate_number' => '11223344', 'region_code' => '55'],
                [
                    'plate' => '11 ایران 223 44',
                    'paper_id' => 'P1',
                    'page_count' => 1,
                    'payment_id' => 'PAY1',
                    'price_status' => 'unpaid',
                    'inquire_price' => '500000',
                    'warning_price' => '0',
                    'ejr_inquire_no' => 'EJR1',
                ],
            ],
            'vehicleInquiryViolationsDetails' => [
                'vehicleInquiryViolationsDetails',
                ['09121234567', '0012345678', '11223344', '55'],
                'services/inquiry/vehicle_inquiry/violations_details',
                ['mobile' => '09121234567', 'national_code' => '0012345678', 'plate_number' => '11223344', 'region_code' => '55'],
                [
                    'warnings' => [
                        [
                            'warning_id' => 'W1',
                            'paper_id' => 'P1',
                            'serial_no' => 'S1',
                            'violation_type' => 'speed',
                            'violatoin_address' => 'تهران',
                            'violation_occure_date' => '1402-01-01',
                            'violation_occure_time' => '10:00',
                            'final_price' => '500000',
                            'has_image' => true,
                            'investigation_ability' => false,
                            'payment_id' => 'PAY1',
                            'violation_delivery_type' => 'sms',
                        ],
                    ],
                ],
            ],
            'enamadInquiry' => [
                'enamadInquiry',
                ['example.com'],
                'services/inquiry/enamad_inquiry',
                ['website' => 'example.com'],
                [
                    'id' => 100,
                    'name' => 'نمونه',
                    'nameper' => 'نمونه فارسی',
                    'domain' => 'example.com',
                    'expired' => false,
                    'expiry_date' => '1404-01-01',
                    'approve_date' => '1401-01-01',
                    'city_name' => 'تهران',
                    'state_name' => 'تهران',
                    'logolevel' => 1,
                    'srv_text' => 'ok',
                    'message' => 'موفق',
                ],
            ],
            'persianToFinglish' => [
                'persianToFinglish',
                ['سلام'],
                'services/inquiry/persian_to_finglish',
                ['persian_text' => 'سلام'],
                ['finglish_text' => 'salaam'],
            ],
            'voiceOtp' => [
                'voiceOtp',
                ['09121234567', '12345'],
                'services/inquiry/voice_otp',
                ['mobile' => '09121234567', 'code' => '12345'],
                [],
            ],
        ];
    }

    // -- Representative business-error paths (post()) --------------------------

    public function testCardInquiryThrowsBusinessExceptionOnErrorCode(): void
    {
        $service = $this->makeService([
            $this->jsonResponse(200, $this->errorEnvelope('CARD_NOT_FOUND', 'کارت وارد شده در سيستم بانکي وجود ندارد')),
        ]);

        try {
            $service->cardInquiry('0000000000000000');
            self::fail('Expected ZohalBusinessException');
        } catch (ZohalBusinessException $e) {
            self::assertSame('CARD_NOT_FOUND', $e->getErrorCode());
            self::assertSame('کارت وارد شده در سيستم بانکي وجود ندارد', $e->getMessage());
        }
    }

    public function testCheckIbanWithNameThrowsBusinessExceptionOnErrorCode(): void
    {
        $service = $this->makeService([
            $this->jsonResponse(200, $this->errorEnvelope('IBAN_NOT_FOUND', 'شبا یافت نشد')),
        ]);

        $this->expectException(ZohalBusinessException::class);
        $service->checkIbanWithName('IR000000000000000000000000', 'ناشناس');
    }

    // -- nationalCardOcr() — the only multipart/file-upload method -------------

    public function testNationalCardOcrSendsFrontOnlyMultipartAndReturnsUnwrappedData(): void
    {
        $frontPath = $this->makeTempFile('front.jpg', 'FAKE_FRONT_IMAGE_BYTES');

        $mockData = ['front' => ['first_name' => 'علی', 'last_name' => 'رضایی'], 'back' => null];
        $service = $this->makeService([
            $this->jsonResponse(200, $this->envelope($mockData)),
        ], $history);

        $result = $service->nationalCardOcr($frontPath);

        self::assertSame($mockData, $result);
        self::assertCount(1, $history);

        $request = $history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertStringEndsWith('services/inquiry/national_card_ocr', (string) $request->getUri());
        self::assertStringStartsWith('multipart/form-data', $request->getHeaderLine('Content-Type'));

        $body = (string) $request->getBody();
        self::assertStringContainsString('name="national_card_front"', $body);
        self::assertStringContainsString('filename="' . basename($frontPath) . '"', $body);
        self::assertStringContainsString('FAKE_FRONT_IMAGE_BYTES', $body);
        self::assertStringNotContainsString('name="national_card_back"', $body);
    }

    public function testNationalCardOcrSendsFrontAndBackMultipartWhenBackProvided(): void
    {
        $frontPath = $this->makeTempFile('front.jpg', 'FAKE_FRONT_IMAGE_BYTES');
        $backPath = $this->makeTempFile('back.jpg', 'FAKE_BACK_IMAGE_BYTES');

        $mockData = ['front' => ['first_name' => 'علی'], 'back' => ['issue_date' => '1400-01-01']];
        $service = $this->makeService([
            $this->jsonResponse(200, $this->envelope($mockData)),
        ], $history);

        $result = $service->nationalCardOcr($frontPath, $backPath);

        self::assertSame($mockData, $result);

        $body = (string) $history[0]['request']->getBody();
        self::assertStringContainsString('name="national_card_front"', $body);
        self::assertStringContainsString('filename="' . basename($frontPath) . '"', $body);
        self::assertStringContainsString('FAKE_FRONT_IMAGE_BYTES', $body);
        self::assertStringContainsString('name="national_card_back"', $body);
        self::assertStringContainsString('filename="' . basename($backPath) . '"', $body);
        self::assertStringContainsString('FAKE_BACK_IMAGE_BYTES', $body);
    }

    public function testNationalCardOcrThrowsBusinessExceptionOnErrorCode(): void
    {
        $frontPath = $this->makeTempFile('front.jpg', 'FAKE_FRONT_IMAGE_BYTES');

        $service = $this->makeService([
            $this->jsonResponse(200, $this->errorEnvelope('OCR_FAILED', 'تصویر خوانا نیست')),
        ]);

        try {
            $service->nationalCardOcr($frontPath);
            self::fail('Expected ZohalBusinessException');
        } catch (ZohalBusinessException $e) {
            self::assertSame('OCR_FAILED', $e->getErrorCode());
            self::assertSame('تصویر خوانا نیست', $e->getMessage());
        }
    }
}

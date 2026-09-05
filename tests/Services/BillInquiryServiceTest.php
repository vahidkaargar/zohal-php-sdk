<?php

declare(strict_types=1);

namespace Zohal\Sdk\Tests\Services;

use PHPUnit\Framework\TestCase;
use Zohal\Sdk\Exceptions\ZohalBusinessException;
use Zohal\Sdk\Services\BillInquiryService;
use Zohal\Sdk\Tests\Support\MocksZohalClient;

final class BillInquiryServiceTest extends TestCase
{
    use MocksZohalClient;

    /**
     * Covers rightel(), mci(), irancell() and fixedLine(): all four POST to
     * their own endpoint with a single "mobile" request field (fixedLine's
     * quirk is that it keeps the "mobile" key even for landline numbers)
     * and all return the same final_term/mid_term shape unwrapped from the
     * response_body.data envelope.
     *
     * @dataProvider carrierBillProvider
     */
    public function testCarrierBillMethodsSendCorrectRequestAndReturnUnwrappedData(
        string $methodName,
        string $arg,
        string $expectedPath,
        array $expectedPayload,
        array $mockData,
    ): void {
        $client = $this->makeMockClient([
            $this->jsonResponse(200, $this->envelope($mockData)),
        ], $history);

        $service = new BillInquiryService($client);
        $result = $service->{$methodName}($arg);

        self::assertCount(1, $history);
        $request = $history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertStringEndsWith($expectedPath, (string) $request->getUri());
        self::assertSame($expectedPayload, json_decode((string) $request->getBody(), true));

        self::assertSame($mockData, $result);
    }

    public static function carrierBillProvider(): array
    {
        // Amounts use a fractional component (.5) rather than a whole
        // number: PHP's json_encode()/json_decode() round-trip collapses a
        // whole-number float like 48000.0 to the int 48000, which would
        // make the assertSame() below fail on type even though the value
        // is correct — a JSON-wire artifact, not a bug in the service.
        $finalMidTerm = [
            'final_term' => ['amount' => 125000.5, 'bill_id' => 'FT-1001', 'payment_id' => 'PAY-2001'],
            'mid_term' => ['amount' => 48000.5, 'bill_id' => 'MT-1001', 'payment_id' => 'PAY-2002'],
        ];

        return [
            'rightel' => [
                'rightel',
                '09300000000',
                'services/inquiry/bill/rightel',
                ['mobile' => '09300000000'],
                $finalMidTerm,
            ],
            'mci' => [
                'mci',
                '09120000000',
                'services/inquiry/bill/mci',
                ['mobile' => '09120000000'],
                $finalMidTerm,
            ],
            'irancell' => [
                'irancell',
                '09030000000',
                'services/inquiry/bill/irancell',
                ['mobile' => '09030000000'],
                $finalMidTerm,
            ],
            'fixedLine (still sends "mobile" key for the landline number)' => [
                'fixedLine',
                '02112345678',
                'services/inquiry/bill/fixed_line',
                ['mobile' => '02112345678'],
                $finalMidTerm,
            ],
        ];
    }

    /**
     * Covers gas(), water() and electricity(): all three POST to their own
     * endpoint with a single "bill_id" request field and return a flat set
     * of account fields unwrapped from the response_body.data envelope.
     *
     * @dataProvider utilityBillProvider
     */
    public function testUtilityBillMethodsSendCorrectRequestAndReturnUnwrappedData(
        string $methodName,
        string $arg,
        string $expectedPath,
        array $expectedPayload,
        array $mockData,
    ): void {
        $client = $this->makeMockClient([
            $this->jsonResponse(200, $this->envelope($mockData)),
        ], $history);

        $service = new BillInquiryService($client);
        $result = $service->{$methodName}($arg);

        self::assertCount(1, $history);
        $request = $history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertStringEndsWith($expectedPath, (string) $request->getUri());
        self::assertSame($expectedPayload, json_decode((string) $request->getBody(), true));

        self::assertSame($mockData, $result);
    }

    public static function utilityBillProvider(): array
    {
        return [
            'gas' => [
                'gas',
                'GAS-BILL-001',
                'services/inquiry/bill/gas',
                ['bill_id' => 'GAS-BILL-001'],
                [
                    'bill_id' => 'GAS-BILL-001',
                    'full_name' => 'حمید رضایی',
                    'address' => 'تهران، خیابان آزادی',
                    'consumption_type' => 'خانگی',
                    'current_reading_date' => '1404/06/12',
                    'previous_reading_date' => '1404/04/10',
                    'amount' => 356000.5,
                    'payment_id' => 'PAY-3001',
                    'payment_date' => '1404/06/15',
                ],
            ],
            'water' => [
                'water',
                'WATER-BILL-002',
                'services/inquiry/bill/water',
                ['bill_id' => 'WATER-BILL-002'],
                [
                    'account_type' => 'خانگی',
                    'full_name' => 'سارا احمدی',
                    'address' => 'اصفهان، خیابان چهارباغ',
                    'bill_id' => 'WATER-BILL-002',
                    'current_date' => '1404/06/01',
                    'previous_date' => '1404/04/01',
                    'amount' => 89000.5,
                    'payment_id' => 'PAY-3002',
                    'payment_date' => '1404/06/05',
                ],
            ],
            'electricity' => [
                'electricity',
                'ELEC-BILL-003',
                'services/inquiry/bill/electricity',
                ['bill_id' => 'ELEC-BILL-003'],
                [
                    'account_type' => 'تجاری',
                    'full_name' => 'شرکت نمونه',
                    'address' => 'شیراز، بلوار زند',
                    'bill_id' => 'ELEC-BILL-003',
                    'current_date' => '1404/06/03',
                    'previous_date' => '1404/04/03',
                    'amount' => 512000.5,
                    'payment_id' => 'PAY-3003',
                    'payment_date' => '1404/06/07',
                ],
            ],
        ];
    }

    public function testRightelThrowsBusinessExceptionOnErrorCode(): void
    {
        $client = $this->makeMockClient([
            $this->jsonResponse(200, $this->errorEnvelope('MOBILE_NOT_FOUND', 'شماره موبایل یافت نشد')),
        ]);

        $service = new BillInquiryService($client);

        try {
            $service->rightel('09300000000');
            self::fail('Expected ZohalBusinessException');
        } catch (ZohalBusinessException $e) {
            self::assertSame('MOBILE_NOT_FOUND', $e->getErrorCode());
            self::assertSame('شماره موبایل یافت نشد', $e->getMessage());
            self::assertSame(200, $e->getHttpStatus());
        }
    }

    public function testGasThrowsBusinessExceptionOnErrorCode(): void
    {
        $client = $this->makeMockClient([
            $this->jsonResponse(200, $this->errorEnvelope('BILL_NOT_FOUND', 'قبض یافت نشد')),
        ]);

        $service = new BillInquiryService($client);

        try {
            $service->gas('GAS-BILL-404');
            self::fail('Expected ZohalBusinessException');
        } catch (ZohalBusinessException $e) {
            self::assertSame('BILL_NOT_FOUND', $e->getErrorCode());
            self::assertSame('قبض یافت نشد', $e->getMessage());
            self::assertSame(200, $e->getHttpStatus());
        }
    }
}

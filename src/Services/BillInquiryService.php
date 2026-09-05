<?php

declare(strict_types=1);

namespace Zohal\Sdk\Services;

use Zohal\Sdk\ZohalClient;

/**
 * Wraps the /services/inquiry/bill/* utility bill inquiry endpoints.
 * Mobile-carrier bills (rightel/mci/irancell/fixed_line) return a
 * final_term + mid_term pair; gas/water/electricity return a flat set of
 * account fields. All dates in the responses are Jalali-calendar strings
 * (e.g. "1404/06/12"), not ISO — keep them as strings.
 */
final class BillInquiryService
{
    public function __construct(private readonly ZohalClient $client)
    {
    }

    /**
     * POST /services/inquiry/bill/rightel — Rightel mobile bill inquiry.
     *
     * @return array{final_term: array{amount: float, bill_id: string, payment_id: string}, mid_term: array{amount: float, bill_id: string, payment_id: string}}
     */
    public function rightel(string $mobile): array
    {
        return $this->client->post('services/inquiry/bill/rightel', [
            'mobile' => $mobile,
        ]);
    }

    /**
     * POST /services/inquiry/bill/mci — Hamrah-e Avval (MCI) mobile bill
     * inquiry.
     *
     * @return array{final_term: array{amount: float, bill_id: string, payment_id: string}, mid_term: array{amount: float, bill_id: string, payment_id: string}}
     */
    public function mci(string $mobile): array
    {
        return $this->client->post('services/inquiry/bill/mci', [
            'mobile' => $mobile,
        ]);
    }

    /**
     * POST /services/inquiry/bill/irancell — Irancell mobile bill inquiry.
     *
     * @return array{final_term: array{amount: float, bill_id: string, payment_id: string}, mid_term: array{amount: float, bill_id: string, payment_id: string}}
     */
    public function irancell(string $mobile): array
    {
        return $this->client->post('services/inquiry/bill/irancell', [
            'mobile' => $mobile,
        ]);
    }

    /**
     * POST /services/inquiry/bill/fixed_line — fixed-line (landline) bill
     * inquiry. The spec names the request field "mobile" even though this
     * endpoint is for landline numbers; kept as-is to match the wire
     * format.
     *
     * @return array{final_term: array{amount: float, bill_id: string, payment_id: string}, mid_term: array{amount: float, bill_id: string, payment_id: string}}
     */
    public function fixedLine(string $lineNumber): array
    {
        return $this->client->post('services/inquiry/bill/fixed_line', [
            'mobile' => $lineNumber,
        ]);
    }

    /**
     * POST /services/inquiry/bill/gas — natural gas bill inquiry.
     *
     * @return array{
     *     bill_id: string,
     *     full_name: string,
     *     address: string,
     *     consumption_type: string,
     *     current_reading_date: string,
     *     previous_reading_date: string,
     *     amount: float,
     *     payment_id: string,
     *     payment_date: string,
     * }
     */
    public function gas(string $billId): array
    {
        return $this->client->post('services/inquiry/bill/gas', [
            'bill_id' => $billId,
        ]);
    }

    /**
     * POST /services/inquiry/bill/water — water utility bill inquiry.
     *
     * @return array{
     *     account_type: string,
     *     full_name: string,
     *     address: string,
     *     bill_id: string,
     *     current_date: string,
     *     previous_date: string,
     *     amount: float,
     *     payment_id: string,
     *     payment_date: string,
     * }
     */
    public function water(string $billId): array
    {
        return $this->client->post('services/inquiry/bill/water', [
            'bill_id' => $billId,
        ]);
    }

    /**
     * POST /services/inquiry/bill/electricity — electricity bill inquiry.
     *
     * @return array{
     *     account_type: string,
     *     full_name: string,
     *     address: string,
     *     bill_id: string,
     *     current_date: string,
     *     previous_date: string,
     *     amount: float,
     *     payment_id: string,
     *     payment_date: string,
     * }
     */
    public function electricity(string $billId): array
    {
        return $this->client->post('services/inquiry/bill/electricity', [
            'bill_id' => $billId,
        ]);
    }
}

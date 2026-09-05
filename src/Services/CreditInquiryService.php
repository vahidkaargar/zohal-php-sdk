<?php

declare(strict_types=1);

namespace Zohal\Sdk\Services;

use Zohal\Sdk\ZohalClient;

/**
 * Wraps the /services/inquiry/credit_inquiry/* flow: request an OTP,
 * verify it, then poll for the credit report by reference_id. Unlike
 * every other service in this SDK, these two endpoints don't use the
 * usual {response_body:{data,...}} envelope:
 *
 *  - send_otp's success response is bare top-level JSON
 *    ({"reference_id": ..., "status": ...}), no response_body at all.
 *  - verify_otp's 200 response has no documented schema whatsoever in
 *    the spec; treat an empty result as success.
 *
 * result() does use the normal envelope.
 */
final class CreditInquiryService
{
    public function __construct(private readonly ZohalClient $client)
    {
    }

    /**
     * POST /services/inquiry/credit_inquiry/send_otp — send an OTP to the
     * given mobile number to start a credit inquiry. The returned
     * reference_id is required by verifyOtp() and result().
     *
     * @return array{reference_id: string, status: string}
     */
    public function sendOtp(string $mobile, string $nationalCode): array
    {
        $body = $this->client->postRaw('services/inquiry/credit_inquiry/send_otp', [
            'mobile' => $mobile,
            'national_code' => $nationalCode,
        ]);

        return [
            'reference_id' => $body['reference_id'] ?? '',
            'status' => $body['status'] ?? '',
        ];
    }

    /**
     * POST /services/inquiry/credit_inquiry/verify_otp — verify the OTP
     * code for a pending credit inquiry. The API documents no response
     * schema for this endpoint; a 2xx status is the only defined signal
     * of success, so this returns whatever (if anything) came back.
     */
    public function verifyOtp(string $referenceId, string $otp): array
    {
        return $this->client->postRaw('services/inquiry/credit_inquiry/verify_otp', [
            'reference_id' => $referenceId,
            'otp' => $otp,
        ]);
    }

    /**
     * GET /services/inquiry/credit_inquiry/result/{reference_id} —
     * fetch the finished credit report for a verified inquiry. `result`
     * is a large, variably-shaped credit-bureau payload (bounced
     * cheques, contracts, score, tax records, ...); typed as a plain
     * array rather than a strict shape given its size.
     *
     * @return array{completed_at: string, reference_id: string, result: array<string, mixed>, service: string, status: string}
     */
    public function result(string $referenceId): array
    {
        return $this->client->get('services/inquiry/credit_inquiry/result/' . rawurlencode($referenceId));
    }
}

<?php

declare(strict_types=1);

namespace Zohal\Sdk\Services;

use Zohal\Sdk\ZohalClient;

/**
 * Wraps every /services/inquiry/* endpoint (banking, cheque, identity,
 * company, vehicle, and text/OCR/OTP inquiries). Each method maps 1:1 to
 * one endpoint from the OpenAPI spec. All endpoints are JSON except
 * nationalCardOcr(), which is multipart/form-data (file upload).
 */
final class InquiryService
{
    public function __construct(private readonly ZohalClient $client)
    {
    }

    // -- Card / account / IBAN -------------------------------------------------

    /**
     * POST /services/inquiry/card_inquiry — resolve the cardholder's name
     * from a bank card number.
     *
     * @return array{name: string}
     */
    public function cardInquiry(string $cardNumber): array
    {
        return $this->client->post('services/inquiry/card_inquiry', [
            'card_number' => $cardNumber,
        ]);
    }

    /**
     * POST /services/inquiry/card_to_iban — convert a bank card number to
     * its IBAN, cardholder name, and issuing bank.
     *
     * @return array{IBAN: string, bank_name: string, name: string}
     */
    public function cardToIban(string $cardNumber): array
    {
        return $this->client->post('services/inquiry/card_to_iban', [
            'card_number' => $cardNumber,
        ]);
    }

    /**
     * POST /services/inquiry/account_to_iban — convert a bank account
     * number to its IBAN.
     *
     * @return array{IBAN: string}
     */
    public function accountToIban(string $bankAccount, string $bankCode): array
    {
        return $this->client->post('services/inquiry/account_to_iban', [
            'bank_account' => $bankAccount,
            'bank_code' => $bankCode,
        ]);
    }

    /**
     * POST /services/inquiry/card_to_account — resolve a bank card number
     * to its account number, bank name, and cardholder name.
     *
     * @return array{bank_account: string, bank_name: string, name: string}
     */
    public function cardToAccount(string $cardNumber): array
    {
        return $this->client->post('services/inquiry/card_to_account', [
            'card_number' => $cardNumber,
        ]);
    }

    /**
     * POST /services/inquiry/iban — resolve an IBAN's account holder name,
     * issuing bank, and transfer eligibility.
     *
     * @return array{name: string, bank_name: string, is_transferable: bool}
     */
    public function iban(string $iban): array
    {
        return $this->client->post('services/inquiry/iban', [
            'iban' => $iban,
        ]);
    }

    /**
     * POST /services/inquiry/check_card_with_name — verify that a bank
     * card number belongs to the given cardholder name.
     *
     * @return array{name: string}
     */
    public function checkCardWithName(string $cardNumber, string $name): array
    {
        return $this->client->post('services/inquiry/check_card_with_name', [
            'card_number' => $cardNumber,
            'name' => $name,
        ]);
    }

    /**
     * POST /services/inquiry/check_iban_with_name — verify that an IBAN
     * belongs to the given account holder name.
     *
     * @return array{matched: bool}
     */
    public function checkIbanWithName(string $iban, string $name): array
    {
        return $this->client->post('services/inquiry/check_iban_with_name', [
            'IBAN' => $iban,
            'name' => $name,
        ]);
    }

    /**
     * POST /services/inquiry/check_iban_with_national_code — verify that
     * an IBAN belongs to the given national code and birth date.
     *
     * @return array{matched: bool}
     */
    public function checkIbanWithNationalCode(string $iban, string $nationalCode, string $birthDate): array
    {
        return $this->client->post('services/inquiry/check_iban_with_national_code', [
            'IBAN' => $iban,
            'national_code' => $nationalCode,
            'birth_date' => $birthDate,
        ]);
    }

    /**
     * POST /services/inquiry/check_card_with_national_code — verify that a
     * bank card number belongs to the given national code and birth date.
     *
     * @return array{matched: bool}
     */
    public function checkCardWithNationalCode(string $cardNumber, string $nationalCode, string $birthDate): array
    {
        return $this->client->post('services/inquiry/check_card_with_national_code', [
            'card_number' => $cardNumber,
            'national_code' => $nationalCode,
            'birth_date' => $birthDate,
        ]);
    }

    // -- Cheque / Sayad ---------------------------------------------------------

    /**
     * POST /services/inquiry/check_sayad_inquiry — look up a Sayad
     * (sayadi) cheque's details from its sayad_id.
     *
     * @return array{
     *     sayad_id: string,
     *     iban: string,
     *     name: string,
     *     serial_no: string,
     *     series_no: string,
     *     check_type: string,
     *     issue_date: string,
     *     branch_code: string,
     *     expiration_date: ?string,
     *     returned_cheques: string,
     * }
     */
    public function checkSayadInquiry(string $sayadId): array
    {
        return $this->client->post('services/inquiry/check_sayad_inquiry', [
            'sayad_id' => $sayadId,
        ]);
    }

    /**
     * POST /services/inquiry/check_sayad_inquiry/chain — retrieve the
     * endorsement chain of a Sayad cheque.
     *
     * @return array{chain: list<array{role_type: int, customers: list<array{customer_type: mixed, name: string, national_code: string}>}>}
     */
    public function checkSayadInquiryChain(string $chequeType, string $nationalCode, string $sayadId): array
    {
        return $this->client->post('services/inquiry/check_sayad_inquiry/chain', [
            'cheque_type' => $chequeType,
            'national_code' => $nationalCode,
            'sayad_id' => $sayadId,
        ]);
    }

    /**
     * POST /services/inquiry/bounced_cheque — count bounced (dishonored)
     * cheques recorded against a person.
     *
     * @return array{count: int}
     */
    public function bouncedCheque(string $nationalCode, int $nationalityType): array
    {
        return $this->client->post('services/inquiry/bounced_cheque', [
            'national_code' => $nationalCode,
            'nationality_type' => $nationalityType,
        ]);
    }

    // -- Identity ----------------------------------------------------------------

    /**
     * POST /services/inquiry/shahkar — verify a mobile number belongs to
     * the given national code.
     *
     * @return array{matched: bool}
     */
    public function shahkar(string $mobile, string $nationalCode): array
    {
        return $this->client->post('services/inquiry/shahkar', [
            'mobile' => $mobile,
            'national_code' => $nationalCode,
        ]);
    }

    /**
     * POST /services/inquiry/national_identity_inquiry — verify a national
     * code and birth date against civil registry records.
     *
     * @return array{
     *     matched: bool,
     *     first_name: ?string,
     *     last_name: ?string,
     *     father_name: ?string,
     *     national_code: ?string,
     *     alive: ?bool,
     *     is_dead: ?bool,
     * }
     */
    public function nationalIdentityInquiry(string $nationalCode, string $birthDate): array
    {
        return $this->client->post('services/inquiry/national_identity_inquiry', [
            'national_code' => $nationalCode,
            'birth_date' => $birthDate,
        ]);
    }

    // -- Company -------------------------------------------------------------

    /**
     * POST /services/inquiry/company_inquiry — look up a company's
     * official registry information from its national ID.
     *
     * @return array{
     *     name: string,
     *     national_id: string,
     *     company_type: string,
     *     register_date: string,
     *     register_number: string,
     *     issuance_date: string,
     *     created_at: string,
     *     address: string,
     *     postal_code: string,
     *     phone_number: ?string,
     *     fax_number: string,
     *     email_address: string,
     *     activity_end_date: string,
     * }
     */
    public function companyInquiry(string $nationalId): array
    {
        return $this->client->post('services/inquiry/company_inquiry', [
            'national_id' => $nationalId,
        ]);
    }

    /**
     * POST /services/inquiry/company_inquiry/board_members — list a
     * company's current board members and inspectors.
     *
     * @return array{company_title: string, board_members: list<array{name: string, position: string, start_date: string, duration: string}>}
     */
    public function companyInquiryBoardMembers(string $nationalId): array
    {
        return $this->client->post('services/inquiry/company_inquiry/board_members', [
            'national_id' => $nationalId,
        ]);
    }

    /**
     * POST /services/inquiry/company_inquiry/board_members/history — list
     * a company's historical board member and inspector records.
     *
     * @return array{company_title: string, board_members: list<array{position: string, name: string, start_date: string, duration: string, end_date: string}>}
     */
    public function companyInquiryBoardMembersHistory(string $nationalId): array
    {
        return $this->client->post('services/inquiry/company_inquiry/board_members/history', [
            'national_id' => $nationalId,
        ]);
    }

    // -- Postal / vehicle / eNamad ---------------------------------------------

    /**
     * POST /services/inquiry/postal_code_inquiry — resolve a postal code
     * to its full address.
     *
     * @return array{address: array{province: string, town: string, street: string, street2: string, number: string, floor: string, side_floor: string, district: string, building_name: string, description: string}}
     */
    public function postalCodeInquiry(string $postalCode): array
    {
        return $this->client->post('services/inquiry/postal_code_inquiry', [
            'postal_code' => $postalCode,
        ]);
    }

    /**
     * POST /services/inquiry/vehicle_inquiry/total_violations — total
     * outstanding traffic violations for a vehicle.
     *
     * @return array{plate: string, paper_id: string, page_count: int, payment_id: string, price_status: string, inquire_price: string, warning_price: string, ejr_inquire_no: string}
     */
    public function vehicleInquiryTotalViolations(
        string $mobile,
        string $nationalCode,
        string $plateNumber,
        string $regionCode,
    ): array {
        return $this->client->post('services/inquiry/vehicle_inquiry/total_violations', [
            'mobile' => $mobile,
            'national_code' => $nationalCode,
            'plate_number' => $plateNumber,
            'region_code' => $regionCode,
        ]);
    }

    /**
     * POST /services/inquiry/vehicle_inquiry/violations_details —
     * itemized traffic violations for a vehicle.
     *
     * @return array{warnings: list<array{warning_id: string, paper_id: string, serial_no: string, violation_type: string, violatoin_address: string, violation_occure_date: string, violation_occure_time: string, final_price: string, has_image: mixed, investigation_ability: mixed, payment_id: string, violation_delivery_type: mixed}>}
     */
    public function vehicleInquiryViolationsDetails(
        string $mobile,
        string $nationalCode,
        string $plateNumber,
        string $regionCode,
    ): array {
        return $this->client->post('services/inquiry/vehicle_inquiry/violations_details', [
            'mobile' => $mobile,
            'national_code' => $nationalCode,
            'plate_number' => $plateNumber,
            'region_code' => $regionCode,
        ]);
    }

    /**
     * POST /services/inquiry/enamad_inquiry — look up a website's Enamad
     * (electronic trust mark) status.
     *
     * @return array{id: int, name: string, nameper: string, domain: string, expired: bool, expiry_date: string, approve_date: string, city_name: string, state_name: string, logolevel: int, srv_text: string, message: string}
     */
    public function enamadInquiry(string $website): array
    {
        return $this->client->post('services/inquiry/enamad_inquiry', [
            'website' => $website,
        ]);
    }

    // -- Text / OCR / OTP ------------------------------------------------------

    /**
     * POST /services/inquiry/persian_to_finglish — transliterate Persian
     * text into Finglish (romanized Persian).
     *
     * @return array{finglish_text: string}
     */
    public function persianToFinglish(string $persianText): array
    {
        return $this->client->post('services/inquiry/persian_to_finglish', [
            'persian_text' => $persianText,
        ]);
    }

    /**
     * POST /services/inquiry/national_card_ocr — OCR an Iranian national
     * ID card image. Unlike every other inquiry endpoint this is
     * multipart/form-data, so it takes file paths rather than scalars.
     *
     * @return array{front: array<string, mixed>, back: ?array<string, mixed>}
     */
    public function nationalCardOcr(string $frontImagePath, ?string $backImagePath = null): array
    {
        $parts = [
            [
                'name' => 'national_card_front',
                'contents' => fopen($frontImagePath, 'rb'),
                'filename' => basename($frontImagePath),
            ],
        ];

        if ($backImagePath !== null) {
            $parts[] = [
                'name' => 'national_card_back',
                'contents' => fopen($backImagePath, 'rb'),
                'filename' => basename($backImagePath),
            ];
        }

        return $this->client->postMultipart('services/inquiry/national_card_ocr', $parts);
    }

    /**
     * POST /services/inquiry/voice_otp — validate a voice/SMS OTP code
     * sent to a mobile number. The API returns no `data` payload; success
     * or failure is conveyed entirely by whether this call returns or
     * throws.
     */
    public function voiceOtp(string $mobile, string $code): array
    {
        return $this->client->post('services/inquiry/voice_otp', [
            'mobile' => $mobile,
            'code' => $code,
        ]);
    }
}

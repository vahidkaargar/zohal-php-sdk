# Zohal PHP SDK

[![Tests](https://github.com/vahidkaargar/zohal-php-sdk/actions/workflows/tests.yml/badge.svg)](https://github.com/vahidkaargar/zohal-php-sdk/actions/workflows/tests.yml)
[![Packagist Version](https://img.shields.io/packagist/v/vahidkaargar/zohal-php-sdk.svg)](https://packagist.org/packages/vahidkaargar/zohal-php-sdk)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

PHP client for the [Zohal](https://zohal.io) API — bank card/IBAN inquiries, cheque and identity verification, company registry lookups, utility bill inquiries, credit scoring, and biometric (Liveness) video authentication.

## Requirements

- PHP 8.1+
- Composer

## Installation

```bash
composer require vahidkaargar/zohal-php-sdk
```

Published on [Packagist](https://packagist.org/packages/vahidkaargar/zohal-php-sdk). To work from this repo directly instead, run `composer install` here, or add it as a [VCS repository](https://getcomposer.org/doc/05-repositories.md#vcs) in a consuming project's `composer.json`.

## Quick start

Every service class takes a `ZohalClient`, which holds your bearer token:

```php
use Zohal\Sdk\ZohalClient;
use Zohal\Sdk\Services\InquiryService;

$client = new ZohalClient(token: 'YOUR_ZOHAL_TOKEN');
$inquiry = new InquiryService($client);

$data = $inquiry->cardInquiry('6362XXXXXXX11');
echo $data['name']; // cardholder name
```

By default requests go to `https://service.zohal.io/api/v0`. Override it (e.g. for a sandbox environment) via the second constructor argument:

```php
$client = new ZohalClient(token: 'YOUR_TOKEN', baseUri: 'https://sandbox.zohal.io/api/v0');
```

## Services

| Class | Covers |
|---|---|
| `InquiryService` | Card/account/IBAN, cheque/Sayad, national identity, company registry, postal code, vehicle violations, eNamad, Persian→Finglish, national-card OCR, voice OTP |
| `BillInquiryService` | Mobile carrier, fixed-line, gas, water, and electricity bill lookups |
| `CreditInquiryService` | Credit-report request flow: send OTP → verify OTP → fetch report |
| `BiometricService` | Selfie-video upload + Liveness session for video authentication |

Every method returns a plain `array` (the API response's unwrapped data) or throws a `ZohalException` — see [Error handling](#error-handling). All arguments are `string` unless noted otherwise. Array shapes below use `{...}` for an associative array and `[...]` for a list.

### InquiryService — 24 methods

```php
use Zohal\Sdk\Services\InquiryService;

$inquiry = new InquiryService($client);
$data = $inquiry->cardInquiry('6362XXXXXXX11');
echo $data['name'];
```

All methods are plain JSON `POST` requests except `nationalCardOcr()`, which is `multipart/form-data`.

#### Card / account / IBAN

| Method | Parameters | Returns |
|---|---|---|
| `cardInquiry` | `$cardNumber` | `{name}` |
| `cardToIban` | `$cardNumber` | `{IBAN, bank_name, name}` |
| `accountToIban` | `$bankAccount, $bankCode` | `{IBAN}` |
| `cardToAccount` | `$cardNumber` | `{bank_account, bank_name, name}` |
| `iban` | `$iban` | `{name, bank_name, is_transferable: bool}` |
| `checkCardWithName` | `$cardNumber, $name` | `{name}` |
| `checkIbanWithName` | `$iban, $name` | `{matched: bool}` |
| `checkIbanWithNationalCode` | `$iban, $nationalCode, $birthDate` | `{matched: bool}` |
| `checkCardWithNationalCode` | `$cardNumber, $nationalCode, $birthDate` | `{matched: bool}` |

#### Cheque / Sayad

| Method | Parameters | Returns |
|---|---|---|
| `checkSayadInquiry` | `$sayadId` | `{sayad_id, iban, name, serial_no, series_no, check_type, issue_date, branch_code, expiration_date: ?string, returned_cheques}` |
| `checkSayadInquiryChain` | `$chequeType, $nationalCode, $sayadId` | `{chain: [{role_type: int, customers: [{customer_type, name, national_code}]}]}` |
| `bouncedCheque` | `$nationalCode, $nationalityType: int` | `{count: int}` |

#### Identity

| Method | Parameters | Returns |
|---|---|---|
| `shahkar` | `$mobile, $nationalCode` | `{matched: bool}` |
| `nationalIdentityInquiry` | `$nationalCode, $birthDate` | `{matched: bool, first_name: ?string, last_name: ?string, father_name: ?string, national_code: ?string, alive: ?bool, is_dead: ?bool}` |

#### Company registry

| Method | Parameters | Returns |
|---|---|---|
| `companyInquiry` | `$nationalId` | `{name, national_id, company_type, register_date, register_number, issuance_date, created_at, address, postal_code, phone_number: ?string, fax_number, email_address, activity_end_date}` |
| `companyInquiryBoardMembers` | `$nationalId` | `{company_title, board_members: [{name, position, start_date, duration}]}` |
| `companyInquiryBoardMembersHistory` | `$nationalId` | `{company_title, board_members: [{position, name, start_date, duration, end_date}]}` |

#### Postal / vehicle / eNamad

| Method | Parameters | Returns |
|---|---|---|
| `postalCodeInquiry` | `$postalCode` | `{address: {province, town, street, street2, number, floor, side_floor, district, building_name, description}}` |
| `vehicleInquiryTotalViolations` | `$mobile, $nationalCode, $plateNumber, $regionCode` | `{plate, paper_id, page_count: int, payment_id, price_status, inquire_price, warning_price, ejr_inquire_no}` |
| `vehicleInquiryViolationsDetails` | `$mobile, $nationalCode, $plateNumber, $regionCode` | `{warnings: [{warning_id, paper_id, serial_no, violation_type, violatoin_address, violation_occure_date, violation_occure_time, final_price, has_image, investigation_ability, payment_id, violation_delivery_type}]}` |
| `enamadInquiry` | `$website` | `{id: int, name, nameper, domain, expired: bool, expiry_date, approve_date, city_name, state_name, logolevel: int, srv_text, message}` |

#### Text / OCR / OTP

| Method | Parameters | Returns |
|---|---|---|
| `persianToFinglish` | `$persianText` | `{finglish_text}` |
| `nationalCardOcr` | `$frontImagePath, $backImagePath = null` | `{front: {...}, back: ?array}` — **multipart file upload**, the one exception to JSON in this service; `$backImagePath` is optional |
| `voiceOtp` | `$mobile, $code` | `{}` — no `data` payload; success is simply not throwing |

```php
// multipart file upload example
$inquiry->nationalCardOcr('/path/to/front.jpg', '/path/to/back.jpg'); // back is optional
```

### BillInquiryService — 7 methods

```php
use Zohal\Sdk\Services\BillInquiryService;

$bills = new BillInquiryService($client);
$bills->mci('09121234567'); // ['final_term' => [...], 'mid_term' => [...]]
```

All dates in the responses are Jalali-calendar strings (e.g. `"1404/06/12"`), not ISO.

| Method | Parameters | Returns |
|---|---|---|
| `rightel` | `$mobile` | `{final_term: {amount: float, bill_id, payment_id}, mid_term: {amount: float, bill_id, payment_id}}` |
| `mci` | `$mobile` | same shape as `rightel` |
| `irancell` | `$mobile` | same shape as `rightel` |
| `fixedLine` | `$lineNumber` | same shape as `rightel` — the API's own request field is still named `mobile` on the wire even for landline numbers |
| `gas` | `$billId` | `{bill_id, full_name, address, consumption_type, current_reading_date, previous_reading_date, amount: float, payment_id, payment_date}` |
| `water` | `$billId` | `{account_type, full_name, address, bill_id, current_date, previous_date, amount: float, payment_id, payment_date}` |
| `electricity` | `$billId` | same shape as `water` |

### CreditInquiryService — 3 methods

A three-step OTP flow. `sendOtp()` and `verifyOtp()` use non-standard response shapes documented directly on the class (`sendOtp` has no envelope at all; `verifyOtp`'s success response is undocumented by the API itself). `result()` does use the normal envelope.

| Method | Parameters | Returns | Notes |
|---|---|---|---|
| `sendOtp` | `$mobile, $nationalCode` | `{reference_id, status}` | Bare top-level JSON — no `response_body` wrapper at all |
| `verifyOtp` | `$referenceId, $otp` | whatever the API returns, untouched | The spec documents no response schema for this endpoint |
| `result` | `$referenceId` | `{completed_at, reference_id, status, service, result: array}` | Normal envelope; `result` is the full credit-bureau report, deliberately untyped given its size |

```php
use Zohal\Sdk\Services\CreditInquiryService;

$credit = new CreditInquiryService($client);

$otp = $credit->sendOtp('09121234567', '0021234567');
// $otp = ['reference_id' => '...', 'status' => 'pending']

$credit->verifyOtp($otp['reference_id'], '55555'); // user-entered OTP code

// Poll (or wait, then call once) until status is no longer pending:
$report = $credit->result($otp['reference_id']);
// $report = ['completed_at' => ..., 'reference_id' => ..., 'status' => 'completed', 'result' => [ ...credit bureau data... ]]
```

`$report['result']` is a large, variably-shaped credit-bureau payload (bounced cheques, contracts, credit score, tax records, ...) — deliberately typed as a plain array rather than a strict shape.

### BiometricService — 3 methods

Upload a selfie video, start a Liveness session against it, then poll for the verdict (or use your own `callback_url` webhook instead of polling). None of these three use the usual `{response_body:{data,...}}` envelope — fields sit directly under `response_body`.

| Method | Parameters | Returns |
|---|---|---|
| `uploadMedia` | `$videoFilePath, $type = 'selfie_video'` | `{id, type}` |
| `startLivenessSession` | `$selfieVideoMediaId, $nationalCode, $nationalCardSerial, $birthDate, $callbackUrl = null` | `{session_id, status}` |
| `sessionResult` | `$sessionId` | `{completed_at: ?string, reason, result, status, type}` |

```php
use Zohal\Sdk\Services\BiometricService;

// If Zohal issued you a separate token for the video-auth service,
// build a dedicated client/instance for it instead of reusing $client.
$biometric = new BiometricService($client);

$media = $biometric->uploadMedia('/path/to/selfie.mp4');
// $media = ['id' => '<uuid>', 'type' => 'selfie_video']

$session = $biometric->startLivenessSession(
    selfieVideoMediaId: $media['id'],
    nationalCode: '0021234567',
    nationalCardSerial: '1G34567890',
    birthDate: '1370/01/01',
    callbackUrl: 'https://your-app.example/webhooks/zohal-liveness', // optional
);
// $session = ['session_id' => '<uuid>', 'status' => 'pending']

$result = $biometric->sessionResult($session['session_id']);
// $result = ['completed_at' => ?string, 'reason' => 'ACCEPT', 'result' => 'matched', 'status' => 'completed', 'type' => 'liveness']
```

`reason` is one of `ACCEPT`, `REJECT_FACE_NOT_MATCH_ID`, `REJECT_MORE_THAN_ONE_PERSON`, `REJECT_NO_PERSON_DETECTED`, `REJECT_PERSON_TOO_FAR_AWAY`, `REJECT_VIDEO_BAD_LIGHT`, `REJECT_VIDEO_BAD_QUALITY`, `REJECT_VIDEO_NOT_LIVE`, `UNKNOWN`, or `UNDEFINED`.

## Laravel

Supports Laravel 9 through 13 (`illuminate/support` `^9.0|^10.0|^11.0|^12.0|^13.0`). The package ships a service provider that's auto-discovered — nothing to register manually. It binds `ZohalClient` and all four service classes into the container as singletons.

1. Set your token (and optionally a separate one for the biometric service — see below) in `.env`:

    ```dotenv
    ZOHAL_TOKEN=your-token
    # ZOHAL_BASE_URI=https://service.zohal.io/api/v0
    # ZOHAL_BIOMETRIC_TOKEN=your-biometric-token
    ```

2. Optionally publish the config file to tweak it directly:

    ```bash
    php artisan vendor:publish --tag=zohal-config
    ```

3. Inject any service class wherever you need it:

    ```php
    use Zohal\Sdk\Services\InquiryService;

    class WalletController
    {
        public function __construct(private InquiryService $inquiry) {}

        public function show(string $cardNumber)
        {
            return $this->inquiry->cardInquiry($cardNumber);
        }
    }
    ```

    Or resolve it out of the container directly: `app(InquiryService::class)`.

`BiometricService`'s client resolves from a separate `zohal.biometric_client` container binding, which uses `ZOHAL_BIOMETRIC_TOKEN` when set and falls back to `ZOHAL_TOKEN` otherwise — Zohal's video-auth service may be issued its own token independent of the rest of the API.

## Error handling

Every failure — network error, non-2xx HTTP status, or a 2xx response carrying a business `error_code` (e.g. `CARD_NOT_FOUND`) — is thrown as a typed exception instead of being returned silently:

```php
use Zohal\Sdk\Exceptions\ZohalBusinessException;
use Zohal\Sdk\Exceptions\ZohalRequestException;

try {
    $data = $inquiry->cardInquiry('0000000000000000');
} catch (ZohalBusinessException $e) {
    // The HTTP call succeeded but the inquiry itself failed, e.g. CARD_NOT_FOUND
    echo $e->getErrorCode(); // 'CARD_NOT_FOUND'
    echo $e->getMessage();   // API's own message, in Persian
} catch (ZohalRequestException $e) {
    // Network failure, or a non-2xx HTTP status (404/500/503/...)
    echo $e->getHttpStatus();
}
```

Both extend `Zohal\Sdk\Exceptions\ZohalException`, which also exposes `getContext()` (the request path and raw decoded response body, where available) for logging.

## Testing

```bash
composer install
composer test   # runs vendor/bin/phpunit
```

Every HTTP call is mocked (`GuzzleHttp\Handler\MockHandler`) — no network access, no real Zohal credentials needed. Coverage includes every public method on every service class (request path, payload/multipart fields, and the unwrapped return value), the client's handling of all three response envelope shapes the real API actually uses (data-wrapped, bare, and no-`data`-key), business vs. transport error handling, and the Laravel service provider's container bindings.

## CI

[.github/workflows/tests.yml](.github/workflows/tests.yml) runs on every push and pull request:

- **Lint** — `composer validate --strict` + a PHP syntax check, gating everything else.
- **Tests** — the full suite on PHP 8.1 through 8.4. Each PHP version resolves its own dependency set fresh (`composer config platform.php <version>` then `composer update`, not a shared lock file), so the 8.3/8.4 legs genuinely exercise Laravel 13 while 8.1/8.2 exercise the older Laravel versions they're actually capped at.
- **Lowest dependency versions** — the suite again with `composer update --prefer-lowest`, to catch code that only works with newer-than-declared dependencies.
- **Security audit** — `composer audit` against the resolved dependency tree.

## Development

```bash
composer install
php -l src/**/*.php   # syntax check
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for how to set up the project, the expected test coverage for a change, and the code style this repo follows. Bug reports and pull requests are welcome.

## License

MIT — see [LICENSE](LICENSE).

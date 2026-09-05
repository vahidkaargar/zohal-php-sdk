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

(Once published on Packagist. Until then, or to work from this repo directly, run `composer install` here, or add it as a [VCS repository](https://getcomposer.org/doc/05-repositories.md#vcs) in a consuming project's `composer.json`.)

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

### InquiryService

```php
use Zohal\Sdk\Services\InquiryService;

$inquiry = new InquiryService($client);

$inquiry->cardInquiry('6362XXXXXXX11');                 // ['name' => ...]
$inquiry->cardToIban('6362XXXXXXX11');                   // ['IBAN' => ..., 'bank_name' => ..., 'name' => ...]
$inquiry->iban('IR96056061182xxxxxxxxxxxx1');            // ['name' => ..., 'bank_name' => ..., 'is_transferable' => bool]
$inquiry->shahkar('09121234567', '0021234567');          // ['matched' => bool]
$inquiry->nationalIdentityInquiry('0021234567', '1370/01/01'); // ['matched' => bool, 'first_name' => ..., ...]
$inquiry->companyInquiry('14001234560');                 // full company registry record
$inquiry->postalCodeInquiry('1234567890');                // ['address' => [...]]
$inquiry->vehicleInquiryTotalViolations('09121234567', '0021234567', '11ب111', '11');

// File upload (multipart) — the one exception to JSON in this service
$inquiry->nationalCardOcr('/path/to/front.jpg', '/path/to/back.jpg'); // back is optional
```

See [src/Services/InquiryService.php](src/Services/InquiryService.php) for the full list of 24 methods and their return shapes.

### BillInquiryService

```php
use Zohal\Sdk\Services\BillInquiryService;

$bills = new BillInquiryService($client);

$bills->mci('09121234567');        // ['final_term' => [...], 'mid_term' => [...]]
$bills->irancell('09121234567');
$bills->rightel('09121234567');
$bills->fixedLine('02112345678');  // API's own field is named `mobile` even for landline numbers
$bills->gas('1234567890');         // flat fields: address, amount, payment_date, ...
$bills->water('1234567890');
$bills->electricity('1234567890');
```

### CreditInquiryService

A three-step OTP flow. `sendOtp()` and `verifyOtp()` use non-standard response shapes documented directly on the class (`sendOtp` has no envelope at all; `verifyOtp`'s success response is undocumented by the API itself).

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

### BiometricService

Upload a selfie video, start a Liveness session against it, then poll for the verdict (or use your own `callback_url` webhook instead of polling).

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

The package ships a service provider that's auto-discovered — nothing to register manually. It binds `ZohalClient` and all four service classes into the container as singletons.

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
- **Tests** — the full suite on PHP 8.1 through 8.4.
- **Lowest dependency versions** — the suite again with `composer update --prefer-lowest`, to catch code that only works with newer-than-declared dependencies.
- **Security audit** — `composer audit` against the resolved dependency tree.

## Development

```bash
composer install
php -l src/**/*.php   # syntax check
```

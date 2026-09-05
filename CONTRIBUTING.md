# Contributing

Thanks for considering a contribution to the Zohal PHP SDK.

## Getting started

```bash
git clone https://github.com/vahidkaargar/zohal-php-sdk.git
cd zohal-php-sdk
composer install
composer test
```

All tests run against mocked HTTP responses (`GuzzleHttp\Handler\MockHandler`) — no network access or real Zohal credentials are needed to develop or test this package.

## Making a change

1. Fork the repo and create a branch off `main`.
2. Make your change. If you're touching an endpoint's request/response shape, verify it against the actual [Zohal API documentation](https://zohal.io) rather than assuming — several endpoints in this SDK have irregular response envelopes (see `ZohalClient`'s docblock) that were only caught by reading the raw spec.
3. Add or update tests under `tests/` for anything you change. Every public method on every service class should have coverage for its request (path, payload/multipart fields) and its return value; see `tests/Support/MocksZohalClient.php` for the shared mocking helper.
4. Run the full check locally before opening a PR:

    ```bash
    composer validate --strict
    find src tests config -name '*.php' -print0 | xargs -0 -n1 php -l
    composer test
    ```

5. Open a pull request. [CI](.github/workflows/tests.yml) runs the same checks across PHP 8.1–8.4, against the lowest supported dependency versions, and a security audit — all of it needs to pass before merge.

## Code style

- PHP 8.1+, `declare(strict_types=1)` in every file, constructor property promotion, readonly properties where the value never changes after construction.
- No comments explaining *what* code does — only *why*, when it's non-obvious (a workaround for an API quirk, a hidden constraint). Several existing docblocks documenting the API's irregular envelope shapes are good examples of comments worth keeping.
- Match the existing service-class pattern: one method per API endpoint, a `@return array{...}` shape in the docblock, request field names/casing taken exactly from the API (not normalized).

## Reporting an issue

Open a [GitHub issue](https://github.com/vahidkaargar/zohal-php-sdk/issues) with the endpoint involved, the request you sent (redact any real card numbers, national codes, or tokens), and the response or error you got.

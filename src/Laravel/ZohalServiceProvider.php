<?php

declare(strict_types=1);

namespace Zohal\Sdk\Laravel;

use Illuminate\Support\ServiceProvider;
use Zohal\Sdk\Services\BillInquiryService;
use Zohal\Sdk\Services\BiometricService;
use Zohal\Sdk\Services\CreditInquiryService;
use Zohal\Sdk\Services\InquiryService;
use Zohal\Sdk\ZohalClient;

/**
 * Registers the SDK's client and service classes as container singletons,
 * configured from config/zohal.php (published from this package). Package
 * auto-discovery registers this automatically; see composer.json's
 * extra.laravel.providers.
 */
final class ZohalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/zohal.php', 'zohal');

        $this->app->singleton(ZohalClient::class, function ($app) {
            return new ZohalClient(
                token: (string) $app['config']->get('zohal.token'),
                baseUri: (string) $app['config']->get('zohal.base_uri'),
            );
        });

        // The biometric (video-auth) service may use a separate bearer
        // token from the rest of the API; falls back to the main client
        // when no biometric_token is configured.
        $this->app->singleton('zohal.biometric_client', function ($app) {
            $token = $app['config']->get('zohal.biometric_token') ?: $app['config']->get('zohal.token');

            return new ZohalClient(
                token: (string) $token,
                baseUri: (string) $app['config']->get('zohal.base_uri'),
            );
        });

        $this->app->singleton(
            InquiryService::class,
            static fn ($app) => new InquiryService($app->make(ZohalClient::class)),
        );

        $this->app->singleton(
            BillInquiryService::class,
            static fn ($app) => new BillInquiryService($app->make(ZohalClient::class)),
        );

        $this->app->singleton(
            CreditInquiryService::class,
            static fn ($app) => new CreditInquiryService($app->make(ZohalClient::class)),
        );

        $this->app->singleton(
            BiometricService::class,
            static fn ($app) => new BiometricService($app->make('zohal.biometric_client')),
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/zohal.php' => $this->app->configPath('zohal.php'),
            ], 'zohal-config');
        }
    }
}

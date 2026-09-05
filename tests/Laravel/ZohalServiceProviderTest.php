<?php

declare(strict_types=1);

namespace Zohal\Sdk\Tests\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Zohal\Sdk\Laravel\ZohalServiceProvider;
use Zohal\Sdk\Services\BillInquiryService;
use Zohal\Sdk\Services\BiometricService;
use Zohal\Sdk\Services\CreditInquiryService;
use Zohal\Sdk\Services\InquiryService;
use Zohal\Sdk\ZohalClient;

/**
 * Exercises ZohalServiceProvider::register() against a plain
 * Illuminate\Container\Container + Config\Repository, rather than a full
 * Laravel application (avoids pulling in laravel/framework, which isn't
 * needed just to test container bindings).
 */
final class ZohalServiceProviderTest extends TestCase
{
    private function makeContainer(array $zohalConfig): Container
    {
        $container = new Container();
        $container->instance('config', new Repository(['zohal' => $zohalConfig]));

        return $container;
    }

    private function tokenOf(ZohalClient $client): string
    {
        $ref = new \ReflectionProperty($client, 'token');

        return $ref->getValue($client);
    }

    public function testZohalClientResolvesAsSingletonUsingConfiguredToken(): void
    {
        $container = $this->makeContainer([
            'token' => 'main-token',
            'base_uri' => 'https://service.zohal.io/api/v0',
            'biometric_token' => null,
        ]);
        (new ZohalServiceProvider($container))->register();

        $client = $container->make(ZohalClient::class);

        self::assertInstanceOf(ZohalClient::class, $client);
        self::assertSame('main-token', $this->tokenOf($client));
        self::assertSame($client, $container->make(ZohalClient::class));
    }

    /**
     * @dataProvider serviceClassProvider
     */
    public function testEachServiceResolvesAsSingleton(string $serviceClass): void
    {
        $container = $this->makeContainer([
            'token' => 'main-token',
            'base_uri' => 'https://service.zohal.io/api/v0',
            'biometric_token' => null,
        ]);
        (new ZohalServiceProvider($container))->register();

        $service = $container->make($serviceClass);

        self::assertInstanceOf($serviceClass, $service);
        self::assertSame($service, $container->make($serviceClass));
    }

    public static function serviceClassProvider(): array
    {
        return [
            [InquiryService::class],
            [BillInquiryService::class],
            [CreditInquiryService::class],
            [BiometricService::class],
        ];
    }

    public function testBiometricClientFallsBackToMainTokenWhenBiometricTokenUnset(): void
    {
        $container = $this->makeContainer([
            'token' => 'main-token',
            'base_uri' => 'https://service.zohal.io/api/v0',
            'biometric_token' => null,
        ]);
        (new ZohalServiceProvider($container))->register();

        $biometricClient = $container->make('zohal.biometric_client');

        self::assertSame('main-token', $this->tokenOf($biometricClient));
    }

    public function testBiometricClientUsesItsOwnTokenWhenConfigured(): void
    {
        $container = $this->makeContainer([
            'token' => 'main-token',
            'base_uri' => 'https://service.zohal.io/api/v0',
            'biometric_token' => 'bio-token',
        ]);
        (new ZohalServiceProvider($container))->register();

        $mainClient = $container->make(ZohalClient::class);
        $biometricClient = $container->make('zohal.biometric_client');

        self::assertSame('main-token', $this->tokenOf($mainClient));
        self::assertSame('bio-token', $this->tokenOf($biometricClient));
    }

    public function testMergesDefaultConfigWhenAppConfigIsMissingKeys(): void
    {
        $container = new Container();
        $container->instance('config', new Repository([])); // no 'zohal' key at all

        (new ZohalServiceProvider($container))->register();

        self::assertSame(
            'https://service.zohal.io/api/v0',
            $container->make('config')->get('zohal.base_uri'),
        );
    }

    public function testPublishableConfigFileExistsAndReturnsExpectedKeys(): void
    {
        $path = __DIR__ . '/../../config/zohal.php';

        self::assertFileExists($path);

        $config = require $path;

        self::assertIsArray($config);
        self::assertArrayHasKey('token', $config);
        self::assertArrayHasKey('base_uri', $config);
        self::assertArrayHasKey('biometric_token', $config);
        self::assertSame('https://service.zohal.io/api/v0', $config['base_uri']);
    }
}

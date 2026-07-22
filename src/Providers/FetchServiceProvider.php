<?php
namespace Axiom\Providers;

use Axiom\DI\Container;
use Axiom\Contracts\Providers\ServiceProviderInterface;
use Axiom\Contracts\Http\HttpClientInterface;
use Axiom\Http\Client\GuzzleHttpClient;
use Axiom\Http\Client\Fetch;
use GuzzleHttp\Client;

class FetchServiceProvider implements ServiceProviderInterface {
    public function register(Container $container): void {
        $container->bind(
            HttpClientInterface::class, 
            function($c) {
                return new GuzzleHttpClient( 
                    new Client([
                        'timeout' => 10,
                        'verify' => false, // Disable SSL verification for development
                    ])
                );
            }
        );
    }

    public function boot(Container $container): void {
        // Set the default HTTP client for Fetch
        Fetch::setClient($container->make(HttpClientInterface::class));
    }
}
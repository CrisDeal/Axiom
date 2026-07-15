<?php

declare(strict_types=1);

namespace Axiom\Providers;

use Axiom\Config\ConfigRepository;
use Axiom\DI\Container;
use Axiom\Session\SessionManager;
use Axiom\Contracts\Providers\ServiceProviderInterface;

class SessionServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->singleton(SessionManager::class, fn() => new SessionManager());
    }

    public function boot(Container $container): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        $config = $container->make(ConfigRepository::class);
        $this->configureSession($config);

        if(session_status() === PHP_SESSION_NONE) session_start();
    }

    private function configureSession(ConfigRepository $config): void
    {
        $this->validateConfig($config);

        $name = $config->get('session.name');
        if($name !== null) session_name($name);

        if($config->get('session.path') !== null) {
            session_save_path($config->get('session.path'));
        }

        session_set_cookie_params([
            'lifetime' => (int) $config->get('session.lifetime', 120) * 60,
            'secure' => (bool) $config->get('session.secure', false),
            'httponly' => (bool) $config->get('session.http_only', true),
        ]);
    }

    private function validateConfig(ConfigRepository $config): void
    {
        $path = $config->get('session.path');
        if($path !== null && !is_dir($path)) {
            throw new \RuntimeException("El directorio de sesión especificado en 'session.path' no existe: {$path}");
        }
    }
}
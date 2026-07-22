<?php

namespace Axiom\Security;

use Axiom\Session\SessionManager;
use Axiom\Config\ConfigRepository;

class AuthManager
{
    public function __construct(
        private SessionManager $session,
        private ConfigRepository $config
    ) {}

    public function check(): bool
    {
        $key = $this->config->get('auth.session_key', 'email');
        return $this->session->has($key);
    }

    public function user(): ?string
    {
        $key = $this->config->get('auth.session_key', 'email');
        return $this->session->get($key);
    }
}
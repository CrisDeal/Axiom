<?php

namespace Axiom\Security;

class ApiKeyGenerator {

    public static function generate(
        string $prefix = '',
        int $length = 32
    ): array {

        $public = $prefix . '_' . bin2hex(random_bytes(4));
        $secret = bin2hex(random_bytes($length));
        $fullKey = $public . "." . $secret;

        return [
            'public' => $public,
            'secret' => $secret,
            'full'   => $fullKey
        ];
    }
}
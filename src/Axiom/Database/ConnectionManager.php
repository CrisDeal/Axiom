<?php
declare(strict_types=1);

namespace Axiom\Database;

use Axiom\Contracts\Database\ConnectionInterface;

class ConnectionManager
{
    private static array $connections = [];

    public static function register(string $name, callable $resolver): void
    {
        self::$connections[$name] = [
            'resolver' => $resolver,
            'instance' => null
        ];
    }

    public static function get(string $name): ?ConnectionInterface 
    {
        if(!isset(self::$connections[$name])) {
            return null;
        }

        if(self::$connections[$name]['instance'] === null) {
            self::$connections[$name]['instance'] = 
                call_user_func(
                    self::$connections[$name]['resolver']
                );
        }

        return self::$connections[$name]['instance'];
    }
}
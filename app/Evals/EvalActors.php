<?php

namespace App\Evals;

use App\Models\User;
use InvalidArgumentException;

final class EvalActors
{
    /**
     * @var array<string, string>
     */
    public const KEYS = [
        'acme_admin' => 'acme-admin@example.com',
        'acme_agent' => 'acme-agent@example.com',
        'acme_viewer' => 'acme-viewer@example.com',
        'globex_admin' => 'globex-admin@example.com',
        'globex_agent' => 'globex-agent@example.com',
        'globex_viewer' => 'globex-viewer@example.com',
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::KEYS);
    }

    public static function isValid(string $key): bool
    {
        return array_key_exists($key, self::KEYS);
    }

    public static function email(string $key): string
    {
        if (! self::isValid($key)) {
            throw new InvalidArgumentException("Unknown eval actor [{$key}].");
        }

        return self::KEYS[$key];
    }

    public static function user(string $key): User
    {
        return User::query()->where('email', self::email($key))->firstOrFail();
    }
}

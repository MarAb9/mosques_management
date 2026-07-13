<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Configuration repository.
 *
 * Loads every PHP file in config/ (each returning an array) and exposes
 * values through dot notation: Config::get('database.host').
 *
 * Environment variables are read inside the config files themselves via
 * Config::env(), which keeps the same semantics as the legacy appEnv()
 * helper (empty string counts as "not set") so shared hosts without
 * .env support keep working through the defaults.
 */
final class Config
{
    /** @var array<string, array<string, mixed>> */
    private array $items = [];

    public function __construct(string $configDir)
    {
        foreach (glob($configDir . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            $value = require $file;
            if (is_array($value)) {
                $this->items[$key] = $value;
            }
        }
    }

    /**
     * Read an environment variable with a default, treating an empty
     * string as unset (legacy appEnv() behavior).
     */
    public static function env(string $name, string $default = ''): string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? $default : $value;
    }

    /**
     * Get a config value by "file.key.subkey" dot path.
     */
    public function get(string $path, mixed $default = null): mixed
    {
        $segments = explode('.', $path);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

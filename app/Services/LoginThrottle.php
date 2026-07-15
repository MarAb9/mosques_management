<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class LoginThrottle
{
    public function __construct(private readonly Config $config)
    {
    }

    public function tooManyAttempts(string $username, string $ip): bool
    {
        $attempts = $this->update($username, $ip);

        return count($attempts) >= $this->maxAttempts();
    }

    public function hit(string $username, string $ip): void
    {
        $this->update($username, $ip, true);
    }

    public function clear(string $username, string $ip): void
    {
        $path = $this->path($username, $ip);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** @return list<int> */
    private function update(string $username, string $ip, bool $append = false): array
    {
        $directory = (string) $this->config->get('security.login.cache_path');
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            error_log('Unable to create login throttle directory.');
            return [];
        }

        $handle = @fopen($this->path($username, $ip), 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) fclose($handle);
            error_log('Unable to lock login throttle state.');
            return [];
        }

        $contents = stream_get_contents($handle);
        $decoded = json_decode($contents ?: '[]', true);
        $cutoff = time() - $this->decaySeconds();
        $attempts = array_values(array_filter(
            is_array($decoded) ? $decoded : [],
            static fn (mixed $timestamp): bool => is_int($timestamp) && $timestamp >= $cutoff
        ));

        if ($append) {
            $attempts[] = time();
        }

        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, (string) json_encode($attempts));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return $attempts;
    }

    private function path(string $username, string $ip): string
    {
        $identifier = strtolower(substr(trim($username), 0, 100)) . '|' . $ip;
        return rtrim((string) $this->config->get('security.login.cache_path'), '/\\\\')
            . DIRECTORY_SEPARATOR . hash('sha256', $identifier) . '.json';
    }

    private function maxAttempts(): int
    {
        return max(1, (int) $this->config->get('security.login.max_attempts', 5));
    }

    private function decaySeconds(): int
    {
        return max(60, (int) $this->config->get('security.login.decay_seconds', 900));
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Request;
use App\Core\Session;

final class AuditLogger
{
    public function __construct(
        private readonly Config $config,
        private readonly Session $session,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function record(string $action, string $outcome, Request $request, array $context = []): void
    {
        $path = (string) $this->config->get('security.audit_log');
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            error_log('Unable to create audit log directory.');
            return;
        }

        $entry = [
            'timestamp' => gmdate(DATE_ATOM),
            'action' => $action,
            'outcome' => $outcome,
            'actor' => [
                'user_id' => $this->session->userId(),
                'username' => $this->session->get('username'),
                'role' => $this->session->role() ?: null,
            ],
            'request' => [
                'method' => $request->method(),
                'route' => $request->routePath(),
                'ip' => $request->clientIp(),
                'user_agent' => $request->userAgent(),
            ],
            'context' => $this->redact($context),
        ];

        $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false || file_put_contents($path, $json . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            error_log('Unable to write audit event: ' . $action);
        }
    }

    private function redact(mixed $value, string $key = ''): mixed
    {
        $sensitiveParts = ['password', 'secret', 'token', 'csrf', 'cookie', 'authorization', 'national_id', 'phone'];
        foreach ($sensitiveParts as $part) {
            if ($key !== '' && str_contains(strtolower($key), $part)) {
                return '[REDACTED]';
            }
        }

        if (!is_array($value)) {
            return is_string($value) ? substr($value, 0, 500) : $value;
        }

        $redacted = [];
        foreach ($value as $childKey => $childValue) {
            $redacted[$childKey] = $this->redact($childValue, (string) $childKey);
        }

        return $redacted;
    }
}

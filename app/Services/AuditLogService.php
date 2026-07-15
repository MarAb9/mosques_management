<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class AuditLogService
{
    public function __construct(private readonly Config $config)
    {
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 30, ?string $needle = null): array
    {
        $events = [];
        $path = (string) $this->config->get('security.audit_log');
        if (!is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        for ($i = count($lines) - 1; $i >= 0 && count($events) < $limit; $i--) {
            $event = json_decode($lines[$i], true);
            if (!is_array($event)) {
                continue;
            }

            if ($needle !== null && $needle !== '') {
                $encoded = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
                if (!str_contains(mb_strtolower($encoded), mb_strtolower($needle))) {
                    continue;
                }
            }

            $events[] = $event;
        }

        return $events;
    }

    public function countRecentImportIssues(int $limit = 200): int
    {
        $count = 0;
        foreach ($this->recent($limit) as $event) {
            if (($event['action'] ?? '') === 'mosque.import' && in_array(($event['outcome'] ?? ''), ['failed', 'rejected'], true)) {
                $count++;
            }
        }

        return $count;
    }
}
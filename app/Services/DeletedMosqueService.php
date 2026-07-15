<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Session;
use App\Repositories\MosqueRepository;

final class DeletedMosqueService
{
    public function __construct(
        private readonly Config $config,
        private readonly Session $session,
        private readonly MosqueRepository $mosques,
    ) {
    }

    /** @param array<string, mixed> $mosque */
    public function archive(array $mosque): void
    {
        $entry = [
            'deleted_at' => gmdate(DATE_ATOM),
            'deleted_by' => [
                'user_id' => $this->session->userId(),
                'username' => $this->session->get('username'),
                'role' => $this->session->role(),
            ],
            'mosque' => $mosque,
            'restored_at' => null,
            'restored_by' => null,
        ];

        $this->append($entry);
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 50): array
    {
        $entries = array_reverse($this->readAll());
        $active = array_values(array_filter($entries, static fn (array $entry): bool => empty($entry['restored_at'])));

        return array_slice($active, 0, $limit);
    }

    public function restore(string $registrationNumber): bool
    {
        $entries = $this->readAll();
        $restored = false;

        foreach ($entries as &$entry) {
            $mosque = $entry['mosque'] ?? [];
            if (!is_array($mosque) || (string) ($mosque['registration_number'] ?? '') !== $registrationNumber || !empty($entry['restored_at'])) {
                continue;
            }

            if ($this->mosques->nationalCodeExists((string) ($mosque['national_code'] ?? ''))) {
                return false;
            }

            $this->mosques->insertRestored($mosque);
            $entry['restored_at'] = gmdate(DATE_ATOM);
            $entry['restored_by'] = [
                'user_id' => $this->session->userId(),
                'username' => $this->session->get('username'),
                'role' => $this->session->role(),
            ];
            $restored = true;
            break;
        }
        unset($entry);

        if ($restored) {
            $this->writeAll($entries);
        }

        return $restored;
    }

    /** @param array<string, mixed> $entry */
    private function append(array $entry): void
    {
        $path = $this->path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        file_put_contents($path, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /** @return list<array<string, mixed>> */
    private function readAll(): array
    {
        $path = $this->path();
        if (!is_file($path)) {
            return [];
        }

        $entries = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $entry = json_decode($line, true);
            if (is_array($entry)) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /** @param list<array<string, mixed>> $entries */
    private function writeAll(array $entries): void
    {
        $path = $this->path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $content = '';
        foreach ($entries as $entry) {
            $content .= json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
        }
        file_put_contents($path, $content, LOCK_EX);
    }

    private function path(): string
    {
        return (string) $this->config->get('security.deleted_mosques_log', dirname(__DIR__, 2) . '/storage/cache/deleted-mosques.jsonl');
    }
}
<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class BackupService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string, mixed> */
    public function applicationData(): array
    {
        $pdo = $this->db->pdo();

        return [
            'generated_at' => gmdate(DATE_ATOM),
            'format' => 'mosques-management-json-backup-v1',
            'tables' => [
                'mosques' => $pdo->query('SELECT * FROM mosques ORDER BY registration_number')->fetchAll(),
                'guide_imams' => $this->tableIfExists('guide_imams'),
                'quran_mosques' => $this->tableIfExists('quran_mosques'),
                'quran_responsibles' => $this->tableIfExists('quran_responsibles'),
                'users_without_passwords' => $this->tableIfExists('users', 'SELECT id, username, role, full_name FROM users ORDER BY id'),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function tableIfExists(string $table, ?string $query = null): array
    {
        try {
            return $this->db->pdo()->query($query ?? "SELECT * FROM {$table}")->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }
}
<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * PDO connection factory.
 *
 * Builds the connection exactly like the legacy includes/db.php
 * (same DSN, charset, and error mode) so query behavior is identical.
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        // Reuse a connection opened by the legacy includes/config.php
        // bootstrap if one exists (mixed legacy/new request paths).
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            return $this->pdo = $GLOBALS['pdo'];
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $this->config->get('database.host'),
            $this->config->get('database.port'),
            $this->config->get('database.name')
        );

        try {
            $pdo = new PDO(
                $dsn,
                (string) $this->config->get('database.user'),
                (string) $this->config->get('database.pass')
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            http_response_code(500);
            die('حدث خطأ في الاتصال بقاعدة البيانات');
        }

        // Legacy helpers (e.g. processMosqueFormData) expect a global $pdo.
        $GLOBALS['pdo'] = $pdo;

        return $this->pdo = $pdo;
    }
}

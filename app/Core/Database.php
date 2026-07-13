<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * PDO connection factory.
 *
 * Builds the application's PDO connection with utf8mb4 and exception mode.
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

        return $this->pdo = $pdo;
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Data access for the guide_imams reference table.
 */
final class GuideImamRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * All guide imams ordered for dropdowns.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->db->pdo()
            ->query('SELECT id, display_name FROM guide_imams ORDER BY display_name_normalized')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Guide imams with the number of mosques assigned to each
     * (filter dropdown on the mosque list).
     *
     * @return list<array<string, mixed>>
     */
    public function allWithMosqueCounts(): array
    {
        return $this->db->pdo()->query('
            SELECT gi.id, gi.display_name, COUNT(m.registration_number) as mosque_count
            FROM guide_imams gi
            LEFT JOIN mosques m ON gi.id = m.guide_imam_id
            GROUP BY gi.id
            ORDER BY gi.display_name_normalized
        ')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findDisplayName(int $id): string
    {
        $stmt = $this->db->pdo()->prepare('SELECT display_name FROM guide_imams WHERE id = ?');
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();

        return $name === false ? '' : (string) $name;
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class MosqueApiRepository
{
    private const SAFE_SELECT = '
        m.registration_number,
        m.national_code,
        m.mosque_name,
        m.address,
        m.community,
        m.admin_type,
        m.status,
        m.friday_prayer,
        m.construction_date,
        m.latitude,
        m.longitude,
        m.main_image,
        m.quran_memorization,
        m.literacy_program,
        m.guidance_program
    ';

    private const SORT_COLUMNS = [
        'name' => 'm.mosque_name',
        'national_code' => 'm.national_code',
        'community' => 'm.community',
        'status' => 'm.status',
        'construction_year' => 'YEAR(m.construction_date)',
    ];

    public function __construct(private readonly Database $database)
    {
    }

    /** @param array<string, mixed> $filters */
    public function count(array $filters): int
    {
        [$where, $params] = $this->conditions($filters);
        $statement = $this->database->pdo()->prepare('SELECT COUNT(*) FROM mosques m ' . $where);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters, string $sort, string $order, int $offset, int $limit): array
    {
        [$where, $params] = $this->conditions($filters);
        $sortColumn = self::SORT_COLUMNS[$sort] ?? self::SORT_COLUMNS['name'];
        $direction = $order === 'desc' ? 'DESC' : 'ASC';
        $statement = $this->database->pdo()->prepare(
            'SELECT ' . self::SAFE_SELECT . ' FROM mosques m '
            . $where . ' ORDER BY ' . $sortColumn . ' ' . $direction
            . ' LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $name => $value) {
            $statement->bindValue(':' . $name, $value);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public function find(string $identifier): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT ' . self::SAFE_SELECT . '
             FROM mosques m
             WHERE m.registration_number = :registration_number OR m.national_code = :national_code
             ORDER BY (m.national_code = :preferred_code) DESC
             LIMIT 1'
        );
        $statement->execute([
            'registration_number' => $identifier,
            'national_code' => $identifier,
            'preferred_code' => $identifier,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function geoJsonRows(array $filters): array
    {
        $filters['has_coordinates'] = true;
        [$where, $params] = $this->conditions($filters);
        $statement = $this->database->pdo()->prepare(
            'SELECT ' . self::SAFE_SELECT . ' FROM mosques m '
            . $where . ' ORDER BY m.mosque_name ASC'
        );
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{communities: list<string>, statuses: list<string>} */
    public function filterValues(): array
    {
        $pdo = $this->database->pdo();

        return [
            'communities' => $pdo->query(
                'SELECT DISTINCT community FROM mosques
                 WHERE community IS NOT NULL AND community != \'\'
                 ORDER BY community'
            )->fetchAll(PDO::FETCH_COLUMN),
            'statuses' => $pdo->query(
                'SELECT DISTINCT status FROM mosques
                 WHERE status IS NOT NULL AND status != \'\'
                 ORDER BY status'
            )->fetchAll(PDO::FETCH_COLUMN),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function conditions(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (isset($filters['q'])) {
            $searchColumns = ['mosque_name', 'address', 'community', 'national_code'];
            $search = [];
            foreach ($searchColumns as $index => $column) {
                $name = 'query_' . $index;
                $search[] = 'm.' . $column . ' LIKE :' . $name;
                $params[$name] = '%' . $filters['q'] . '%';
            }
            $where[] = '(' . implode(' OR ', $search) . ')';
        }

        foreach (['community', 'status', 'national_code'] as $field) {
            if (isset($filters[$field])) {
                $where[] = 'm.' . $field . ' = :' . $field;
                $params[$field] = $filters[$field];
            }
        }

        if (isset($filters['friday_prayer'])) {
            $where[] = 'm.friday_prayer = :friday_prayer';
            $params['friday_prayer'] = $filters['friday_prayer'] ? 'نعم' : 'لا';
        }

        if (isset($filters['has_coordinates'])) {
            $where[] = $filters['has_coordinates']
                ? 'm.latitude BETWEEN -90 AND 90 AND m.longitude BETWEEN -180 AND 180'
                : '(m.latitude IS NULL OR m.longitude IS NULL
                    OR m.latitude NOT BETWEEN -90 AND 90
                    OR m.longitude NOT BETWEEN -180 AND 180)';
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MosqueRepository;
use App\Repositories\QuranProgramRepository;

/**
 * Dashboard statistics (legacy index.php inline queries).
 */
final class DashboardService
{
    public function __construct(
        private readonly MosqueRepository $mosques,
        private readonly QuranProgramRepository $quranPrograms,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        return [
            'totalMosques' => $this->mosques->countAll(),
            'fridayMosques' => $this->mosques->countFridayMosques(),
            'closedMosques' => $this->mosques->countByStatus('مغلق'),
            'prayerMosques' => $this->mosques->countByStatusNot('مغلق'),
            'quranMosques' => $this->quranPrograms->countAll(),
            'guidanceMosques' => $this->mosques->countGuidanceMosques(),
            'pashalikMosques' => $this->mosques->countByAdminType('pashalik'),
            'circleMosques' => $this->mosques->countByAdminType('circle'),
            'latestMosques' => $this->mosques->latest(5),
        ];
    }
}

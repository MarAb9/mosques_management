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
        private readonly AuditLogService $auditLog,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $totalMosques = $this->mosques->countAll();
        $quranMosques = $this->quranPrograms->countAll();
        $mappedMosques = $this->mosques->countWithCoordinates();
        $quality = $this->mosques->dataQualitySummary();

        return [
            'totalMosques' => $totalMosques,
            'fridayMosques' => $this->mosques->countFridayMosques(),
            'closedMosques' => $this->mosques->countByStatus('مغلق'),
            'prayerMosques' => $this->mosques->countByStatusNot('مغلق'),
            'quranMosques' => $quranMosques,
            'guidanceMosques' => $this->mosques->countGuidanceMosques(),
            'pashalikMosques' => $this->mosques->countByAdminType('pashalik'),
            'circleMosques' => $this->mosques->countByAdminType('circle'),
            'latestMosques' => $this->mosques->latest(5),
            'dataQuality' => $quality,
            'mapCoveragePercent' => $totalMosques > 0 ? round(($mappedMosques / $totalMosques) * 100, 1) : 0,
            'quranCoveragePercent' => $totalMosques > 0 ? round(($quranMosques / $totalMosques) * 100, 1) : 0,
            'mappedMosques' => $mappedMosques,
            'recentAuditEvents' => $this->auditLog->recent(8),
            'recentImportIssues' => $this->auditLog->countRecentImportIssues(),
        ];
    }
}

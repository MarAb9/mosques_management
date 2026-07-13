<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MosqueRepository;
use App\Repositories\QuranProgramRepository;

/**
 * Mosque details payload for the details modal endpoint —
 * same structure as the legacy ajax/get_mosque_details.php response.
 */
final class MosqueDetailsService
{
    public function __construct(
        private readonly MosqueRepository $mosques,
        private readonly QuranProgramRepository $quranPrograms,
    ) {
    }

    /**
     * @return array<string, mixed>|null null when the mosque does not exist
     */
    public function details(string $id): ?array
    {
        $mosque = $this->mosques->findForDetails($id);

        if ($mosque === null) {
            return null;
        }

        $programs = $this->quranPrograms->programsForMosque((string) $mosque['national_code']);

        foreach ($programs as &$program) {
            $program['responsibles'] = $this->quranPrograms->responsiblesForProgram($program['id']);
        }
        unset($program);

        return [
            'registration_number' => $mosque['registration_number'],
            'national_code' => $mosque['national_code'],
            'mosque_name' => $mosque['mosque_name'],
            'address' => $mosque['address'],
            'admin_type' => $mosque['admin_type'],
            'pashalik' => $mosque['pashalik'],
            'circle' => $mosque['circle'],
            'leadership' => $mosque['leadership'],
            'community' => $mosque['community'],
            'construction_year' => $mosque['construction_date'] ? date('Y', strtotime((string) $mosque['construction_date'])) : null,
            'status' => $mosque['status'],
            'friday_prayer' => $mosque['friday_prayer'],
            'funding_source' => $mosque['funding_source'],
            'imam_name' => $mosque['imam_name'],
            'imam_registration' => $mosque['imam_registration'],
            'imam_phone' => $mosque['imam_phone'],
            'preacher_name' => $mosque['preacher_name'],
            'preacher_registration' => $mosque['preacher_registration'],
            'preacher_phone' => $mosque['preacher_phone'],
            'muezzin_name' => $mosque['muezzin_name'],
            'muezzin_registration' => $mosque['muezzin_registration'],
            'muezzin_phone' => $mosque['muezzin_phone'],
            'quran_memorization' => $mosque['quran_memorization'],
            'literacy_program' => $mosque['literacy_program'],
            'guidance_program' => $mosque['guidance_program'],
            'guide_imam' => $mosque['guide_imam'],
            'notes' => $mosque['notes'],
            'administrative_attachment' => $mosque['administrative_attachment'],
            'main_image' => $mosque['main_image'],
            'quran_programs' => $programs,
        ];
    }
}

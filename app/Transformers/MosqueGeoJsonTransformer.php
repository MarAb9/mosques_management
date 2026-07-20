<?php

declare(strict_types=1);

namespace App\Transformers;

final class MosqueGeoJsonTransformer
{
    /** @param array<string, mixed> $mosque
     *  @return array<string, mixed>
     */
    public function transform(array $mosque): array
    {
        return [
            'type' => 'Feature',
            'id' => $mosque['id'],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$mosque['longitude'], $mosque['latitude']],
            ],
            'properties' => [
                'id' => $mosque['id'],
                'national_code' => $mosque['national_code'],
                'name' => $mosque['name'],
                'community' => $mosque['community'],
                'status' => $mosque['status'],
                'friday_prayer' => $mosque['friday_prayer'],
            ],
        ];
    }
}

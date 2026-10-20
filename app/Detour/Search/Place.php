<?php

namespace App\Detour\Search;

/** Un point du trajet : départ ou arrivée */
final readonly class Place
{
    public function __construct(
        public float $lat,
        public float $lon,
        public string $label,
    ) {}

    /** Distance à vol d'oiseau, en km */
    public function distanceTo(float $lat, float $lon): float
    {
        $dLat = deg2rad($lat - $this->lat);
        $dLon = deg2rad($lon - $this->lon);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($this->lat)) * cos(deg2rad($lat)) * sin($dLon / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

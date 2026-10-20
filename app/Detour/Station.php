<?php

namespace App\Detour;

use Carbon\CarbonImmutable;

/** Une station-service du flux officiel (le jeu de données ne donne pas son nom, seulement son adresse) */
final readonly class Station
{
    /**
     * @param  array<string, array{price: float, updated: CarbonImmutable}>  $prices  par carburant (valeur de Fuel)
     * @param  list<string>  $services
     * @param  array<string, string>  $hours  par jour : « 06:00–12:00 », « Fermé » ou « » (non renseigné)
     */
    public function __construct(
        public string $id,
        public string $address,
        public string $city,
        public string $postcode,
        public float $lat,
        public float $lon,
        public bool $highway,
        public array $prices,
        public bool $automate24,
        public array $services,
        public array $hours,
    ) {}

    public function price(Fuel $fuel): ?float
    {
        return $this->prices[$fuel->value]['price'] ?? null;
    }

    public function updatedAt(Fuel $fuel): ?CarbonImmutable
    {
        return $this->prices[$fuel->value]['updated'] ?? null;
    }

    /** Prix de plus de 48 h : affiché, mais signalé */
    public function isStale(Fuel $fuel, CarbonImmutable $now): bool
    {
        $updated = $this->updatedAt($fuel);

        return $updated === null || $updated->lt($now->subHours(48));
    }

    public function label(): string
    {
        return trim(mb_convert_case(mb_strtolower($this->address), MB_CASE_TITLE).', '.$this->city, ', ');
    }
}

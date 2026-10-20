<?php

namespace App\Detour\Search;

use App\Detour\Fuel;

/** Ce que demande l'utilisateur : où il est (et où il va), et son plein */
final readonly class SearchQuery
{
    public function __construct(
        public Place $start,
        public ?Place $destination,
        public Fuel $fuel,
        public float $liters,
        public float $consumption,
        public float $hourValue = 0,
    ) {}
}

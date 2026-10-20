<?php

namespace App\Detour\Search;

use App\Detour\Gain;
use App\Detour\Station;

/** Une station étudiée : son détour réel et son gain par rapport à la station de référence */
final readonly class Candidate
{
    public function __construct(
        public Station $station,
        public float $price,
        public float $detourKm,
        public float $detourMinutes,
        public Gain $gain,
        public bool $isReference,
        public bool $isStale,
    ) {}
}

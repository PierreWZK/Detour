<?php

namespace App\Detour\Search;

use App\Detour\Gain;
use App\Detour\Station;

/** Une station étudiée : son détour réel (total, et en plus de la référence) et son gain par rapport à la référence */
final readonly class Candidate
{
    public function __construct(
        public Station $station,
        public float $price,
        public float $detourKm,
        public float $detourMinutes,
        public float $extraKm,          // détour en plus de celui de la référence (c'est lui qui coûte)
        public float $extraMinutes,
        public Gain $gain,
        public bool $isReference,
        public bool $isStale,
    ) {}
}

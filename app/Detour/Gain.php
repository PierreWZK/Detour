<?php

namespace App\Detour;

/** Le détail d'un détour, en euros (arrondis au centime) et en litres */
final readonly class Gain
{
    public function __construct(
        public float $saving,
        public float $burnedLiters,
        public float $fuelCost,
        public float $timeCost,
        public float $net,
        public ?int $breakEvenLiters,
    ) {}

    public function isWorthIt(): bool
    {
        return $this->net > 0;
    }
}

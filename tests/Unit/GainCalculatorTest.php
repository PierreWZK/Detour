<?php

namespace Tests\Unit;

use App\Detour\GainCalculator;
use PHPUnit\Framework\TestCase;

class GainCalculatorTest extends TestCase
{
    private GainCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new GainCalculator;
    }

    public function test_detour_rentable(): void
    {
        // 40 L, 6,5 L/100 km, 3 km de détour, 5 centimes moins cher
        $gain = $this->calculator->gain(1.799, 1.749, 40, 6.5, 3.0);

        $this->assertSame(2.0, $gain->saving);
        $this->assertSame(0.2, $gain->burnedLiters);       // 0,195 L
        $this->assertSame(0.34, $gain->fuelCost);          // 0,195 × 1,749
        $this->assertSame(1.66, $gain->net);
        $this->assertTrue($gain->isWorthIt());
        $this->assertSame(7, $gain->breakEvenLiters);      // 0,341 € ÷ 0,05 € = 6,8 L
    }

    public function test_station_moins_chere_mais_trop_loin(): void
    {
        $far = $this->calculator->gain(1.799, 1.719, 40, 6.5, 18);
        $near = $this->calculator->gain(1.799, 1.759, 40, 6.5, 1.2);

        $this->assertSame(1.19, $far->net);                // 3,20 − 1,17 L × 1,719
        $this->assertSame(1.46, $near->net);               // 1,60 − 0,078 L × 1,759
        $this->assertGreaterThan($far->net, $near->net);
        $this->assertSame(26, $far->breakEvenLiters);      // 2,011 € ÷ 0,08 € = 25,1 L
    }

    public function test_pas_rentable_quand_la_station_est_plus_chere(): void
    {
        $gain = $this->calculator->gain(1.699, 1.749, 40, 6.5, 2);

        $this->assertSame(-2.0, $gain->saving);
        $this->assertLessThan(0, $gain->net);
        $this->assertFalse($gain->isWorthIt());
        $this->assertNull($gain->breakEvenLiters);
    }

    public function test_prix_egaux(): void
    {
        $gain = $this->calculator->gain(1.749, 1.749, 40, 6.5, 2);

        $this->assertSame(0.0, $gain->saving);
        $this->assertSame(-0.23, $gain->net);              // seul le carburant du détour compte
        $this->assertNull($gain->breakEvenLiters);
    }

    public function test_detour_nul(): void
    {
        $gain = $this->calculator->gain(1.799, 1.749, 40, 6.5, 0);

        $this->assertSame(2.0, $gain->net);
        $this->assertSame(0, $gain->breakEvenLiters);
    }

    public function test_valeur_du_temps(): void
    {
        // 19 minutes à 15 €/h = 4,75 €
        $gain = $this->calculator->gain(1.799, 1.719, 40, 6.5, 18, 19, 15);

        $this->assertSame(4.75, $gain->timeCost);
        $this->assertSame(-3.56, $gain->net);
        $this->assertSame(85, $gain->breakEvenLiters);     // (2,011 + 4,75) ÷ 0,08 = 84,5 L
    }
}

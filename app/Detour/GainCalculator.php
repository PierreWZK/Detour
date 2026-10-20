<?php

namespace App\Detour;

/**
 * Le gain net d'un détour vers une station, par rapport à la station de référence (celle du trajet).
 *
 *   économie       = (prix de référence − prix de la station) × litres
 *   carburant brûlé = détour (km) × consommation (L/100 km) ÷ 100, payé au prix de la station
 *   temps          = durée du détour × valeur de l'heure (facultatif)
 *   gain net       = économie − carburant brûlé − temps
 */
final class GainCalculator
{
    /**
     * @param  float  $referencePrice  prix au litre de la station de référence (€)
     * @param  float  $price  prix au litre de la station étudiée (€)
     * @param  float  $liters  litres à mettre
     * @param  float  $consumption  consommation de la voiture (L/100 km)
     * @param  float  $detourKm  kilomètres en plus pour y aller (aller et retour compris)
     * @param  float  $detourMinutes  minutes en plus
     * @param  float  $hourValue  ce que vaut une heure de son temps (€/h), 0 pour l'ignorer
     */
    public function gain(
        float $referencePrice,
        float $price,
        float $liters,
        float $consumption,
        float $detourKm,
        float $detourMinutes = 0,
        float $hourValue = 0,
    ): Gain {
        $detourKm = max(0, $detourKm);
        $detourMinutes = max(0, $detourMinutes);
        $burnedLiters = $detourKm * $consumption / 100;

        $saving = ($referencePrice - $price) * $liters;
        $fuelCost = $burnedLiters * $price;
        $timeCost = $detourMinutes / 60 * $hourValue;

        return new Gain(
            saving: round($saving, 2),
            burnedLiters: round($burnedLiters, 2),
            fuelCost: round($fuelCost, 2),
            timeCost: round($timeCost, 2),
            net: round($saving - $fuelCost - $timeCost, 2),
            breakEvenLiters: $this->breakEvenLiters($referencePrice, $price, $fuelCost + $timeCost),
        );
    }

    /**
     * À partir de combien de litres le détour devient rentable (arrondi au litre supérieur) ;
     * null si la station n'est pas moins chère : le détour ne sera jamais rentable.
     */
    private function breakEvenLiters(float $referencePrice, float $price, float $detourCost): ?int
    {
        $perLiter = round($referencePrice - $price, 3);
        if ($perLiter <= 0) {
            return null;
        }

        return (int) ceil(round($detourCost / $perLiter, 6));
    }
}

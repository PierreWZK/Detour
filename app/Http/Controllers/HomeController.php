<?php

namespace App\Http\Controllers;

use App\Detour\GainCalculator;
use Illuminate\View\View;

/**
 * La page vitrine. Ses exemples sont calculés par le vrai calculateur :
 * les chiffres affichés sont toujours justes.
 */
class HomeController extends Controller
{
    /** Le plein de l'exemple : 40 litres, une voiture à 6,5 L/100 km, un temps estimé à 15 €/h */
    private const LITERS = 40;

    private const CONSUMPTION = 6.5;

    private const HOUR_VALUE = 15;

    private const REFERENCE_PRICE = 1.799;

    public function __invoke(GainCalculator $calculator): View
    {
        $stations = [
            ['name' => 'La station sur votre route', 'hint' => 'Aucun détour', 'price' => self::REFERENCE_PRICE, 'km' => 0.0, 'minutes' => 0],
            ['name' => 'La station à deux rues', 'hint' => '4 centimes de moins', 'price' => 1.759, 'km' => 1.2, 'minutes' => 2],
            ['name' => 'La moins chère de la ville', 'hint' => '8 centimes de moins', 'price' => 1.719, 'km' => 18.0, 'minutes' => 19],
        ];

        foreach ($stations as &$station) {
            $station['gain'] = $calculator->gain(self::REFERENCE_PRICE, $station['price'], self::LITERS, self::CONSUMPTION, $station['km']);
            $station['gainWithTime'] = $calculator->gain(self::REFERENCE_PRICE, $station['price'], self::LITERS, self::CONSUMPTION, $station['km'], $station['minutes'], self::HOUR_VALUE);
        }
        unset($station);

        return view('home', [
            'hero' => $calculator->gain(self::REFERENCE_PRICE, 1.749, self::LITERS, self::CONSUMPTION, 3.0, 4),
            'stations' => $stations,
            'liters' => self::LITERS,
            'consumption' => self::CONSUMPTION,
            'hourValue' => self::HOUR_VALUE,
            'referencePrice' => self::REFERENCE_PRICE,
        ]);
    }
}

<?php

namespace App\Detour\Search;

use App\Detour\Api\FuelPriceClient;
use App\Detour\Api\RoutingClient;
use App\Detour\GainCalculator;
use App\Detour\Station;
use Carbon\CarbonImmutable;

/**
 * La recherche : les stations autour du trajet, leur détour réel par la route, la station de référence
 * (la moins chère sur le trajet, à défaut la plus proche) et le classement par gain net.
 */
final class DetourSearch
{
    /** Rayon autour du départ quand il n'y a pas de destination */
    public const RADIUS_KM = 10;

    /** Au-delà, la recherche « autour du trajet » n'a plus de sens (le long trajet est un bonus) */
    public const MAX_TRIP_KM = 150;

    /** Détour (aller et retour) en dessous duquel une station est considérée « sur le trajet » : 500 m de chaque côté */
    public const ON_ROUTE_KM = 1.0;

    /** Nombre de stations envoyées au calcul d'itinéraire (le serveur public d'OSRM limite la taille des requêtes) */
    private const ROUTED = 40;

    /** Une route est en moyenne 30 % plus longue que la ligne droite : sert seulement à présélectionner */
    private const ROAD_FACTOR = 1.3;

    public function __construct(
        private readonly FuelPriceClient $prices,
        private readonly RoutingClient $routing,
        private readonly GainCalculator $calculator,
    ) {}

    public function search(SearchQuery $query, ?CarbonImmutable $now = null): SearchResult
    {
        $now ??= CarbonImmutable::now();
        [$centerLat, $centerLon, $radius] = $this->searchArea($query);

        $stations = $this->preselect($query, $this->prices->around($centerLat, $centerLon, $radius, $query->fuel));
        if ($stations === []) {
            return new SearchResult($query, [], null);
        }

        $detours = $this->detours($query, $stations);
        if ($detours === []) {
            return new SearchResult($query, [], null);
        }

        $reference = $this->reference($detours);
        $referencePrice = $reference['price'];

        $candidates = array_map(function (array $d) use ($query, $reference, $referencePrice, $now) {
            $isReference = $d === $reference;

            return new Candidate(
                station: $d['station'],
                price: $d['price'],
                detourKm: round($d['km'], 1),
                detourMinutes: round($d['minutes']),
                // La référence est sur le trajet : c'est le point de comparaison, son gain vaut 0
                gain: $isReference
                    ? $this->calculator->gain($referencePrice, $referencePrice, $query->liters, $query->consumption, 0)
                    : $this->calculator->gain($referencePrice, $d['price'], $query->liters, $query->consumption, $d['km'], $d['minutes'], $query->hourValue),
                isReference: $isReference,
                isStale: $d['station']->isStale($query->fuel, $now),
            );
        }, $detours);

        // Meilleur gain d'abord ; à gain égal, la référence (pas de détour), puis le plus petit détour
        $rank = fn (Candidate $c) => [$c->gain->net, $c->isReference, -$c->detourKm];
        usort($candidates, fn (Candidate $a, Candidate $b) => $rank($b) <=> $rank($a));

        return new SearchResult($query, $candidates, current(array_filter($candidates, fn (Candidate $c) => $c->isReference)) ?: null);
    }

    /** @return array{0: float, 1: float, 2: float} centre et rayon (km) de la zone où chercher */
    private function searchArea(SearchQuery $query): array
    {
        if ($query->destination === null) {
            return [$query->start->lat, $query->start->lon, self::RADIUS_KM];
        }

        $trip = $query->start->distanceTo($query->destination->lat, $query->destination->lon);
        if ($trip > self::MAX_TRIP_KM) {
            throw new TripTooLong(sprintf('Trajet de %d km à vol d\'oiseau : Détour cherche autour des trajets de %d km au plus.', $trip, self::MAX_TRIP_KM));
        }

        return [
            ($query->start->lat + $query->destination->lat) / 2,
            ($query->start->lon + $query->destination->lon) / 2,
            $trip / 2 + 5,
        ];
    }

    /**
     * Garde les stations les plus prometteuses avant le calcul d'itinéraire : le meilleur coût estimé
     * (plein + détour à vol d'oiseau), plus les plus proches du trajet pour ne pas rater la référence.
     *
     * @param  list<Station>  $stations
     * @return list<Station>
     */
    private function preselect(SearchQuery $query, array $stations): array
    {
        $estimates = array_map(function (Station $s) use ($query) {
            $km = $this->straightDetour($query, $s) * self::ROAD_FACTOR;
            $price = $s->price($query->fuel);

            return ['station' => $s, 'km' => $km, 'cost' => $price * $query->liters + $km * $query->consumption / 100 * $price];
        }, $stations);

        usort($estimates, fn ($a, $b) => $a['cost'] <=> $b['cost']);
        $kept = array_slice($estimates, 0, self::ROUTED - 5);

        usort($estimates, fn ($a, $b) => $a['km'] <=> $b['km']);
        foreach (array_slice($estimates, 0, 5) as $near) {
            $kept[] = $near;
        }

        $unique = [];
        foreach ($kept as $e) {
            $unique[$e['station']->id] = $e['station'];
        }

        return array_values($unique);
    }

    private function straightDetour(SearchQuery $query, Station $s): float
    {
        $toStation = $query->start->distanceTo($s->lat, $s->lon);
        if ($query->destination === null) {
            return 2 * $toStation;
        }

        return max(0, $toStation
            + $query->destination->distanceTo($s->lat, $s->lon)
            - $query->start->distanceTo($query->destination->lat, $query->destination->lon));
    }

    /**
     * Détour réel par la route, en une requête : (départ → station → arrivée) − (départ → arrivée).
     * Sans destination, l'arrivée est le départ : c'est l'aller-retour.
     *
     * @param  list<Station>  $stations
     * @return list<array{station: Station, price: float, km: float, minutes: float}>
     */
    private function detours(SearchQuery $query, array $stations): array
    {
        $points = [[$query->start->lat, $query->start->lon]];
        if ($query->destination !== null) {
            $points[] = [$query->destination->lat, $query->destination->lon];
        }
        $end = count($points) - 1;
        $offset = count($points);
        foreach ($stations as $s) {
            $points[] = [$s->lat, $s->lon];
        }

        $stationIndexes = range($offset, $offset + count($stations) - 1);
        $table = $this->routing->table($points, [0, ...$stationIndexes], [...$stationIndexes, $end]);

        // Colonnes : les stations, puis l'arrivée ; lignes : le départ, puis les stations
        $n = count($stations);
        $direct = ['s' => $table['durations'][0][$n] ?? 0, 'm' => $table['distances'][0][$n] ?? 0];

        $detours = [];
        foreach ($stations as $i => $station) {
            $go = ['s' => $table['durations'][0][$i] ?? null, 'm' => $table['distances'][0][$i] ?? null];
            $back = ['s' => $table['durations'][$i + 1][$n] ?? null, 'm' => $table['distances'][$i + 1][$n] ?? null];
            if (in_array(null, [$go['s'], $go['m'], $back['s'], $back['m']], true)) {
                continue; // station injoignable par la route
            }

            $detours[] = [
                'station' => $station,
                'price' => $station->price($query->fuel),
                'km' => max(0, ($go['m'] + $back['m'] - $direct['m']) / 1000),
                'minutes' => max(0, ($go['s'] + $back['s'] - $direct['s']) / 60),
            ];
        }

        return $detours;
    }

    /**
     * La station de référence : la moins chère sur le trajet (détour de 1 km au plus), à défaut la plus proche.
     *
     * @param  list<array{station: Station, price: float, km: float, minutes: float}>  $detours
     * @return array{station: Station, price: float, km: float, minutes: float}
     */
    private function reference(array $detours): array
    {
        $onRoute = array_filter($detours, fn ($d) => $d['km'] <= self::ON_ROUTE_KM);
        $pool = $onRoute !== [] ? $onRoute : $detours;

        usort($pool, fn ($a, $b) => $onRoute !== []
            ? [$a['price'], $a['km']] <=> [$b['price'], $b['km']]
            : [$a['km'], $a['price']] <=> [$b['km'], $b['price']]);

        return $pool[0];
    }
}

<?php

namespace App\Detour\Api;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Itinéraires par la route : service `table` d'OSRM, qui donne en une requête les durées et distances
 * entre des points de départ et des points d'arrivée.
 *
 * shortcut: serveur public de démonstration d'OSRM (usage limité), d'où le cache d'une journée ;
 * passer à OpenRouteService ou à son propre OSRM si les limites gênent.
 */
final class RoutingClient
{
    public function __construct(
        private readonly string $baseUrl = 'https://router.project-osrm.org',
    ) {}

    /**
     * Matrice des trajets en voiture.
     *
     * @param  list<array{0: float, 1: float}>  $points  [lat, lon]
     * @param  list<int>  $sources  indices des points de départ
     * @param  list<int>  $destinations  indices des points d'arrivée
     * @return array{durations: list<list<float|null>>, distances: list<list<float|null>>} en secondes et en mètres
     */
    public function table(array $points, array $sources, array $destinations): array
    {
        $coordinates = implode(';', array_map(fn ($p) => sprintf('%.5F,%.5F', $p[1], $p[0]), $points));
        $query = [
            'sources' => implode(';', $sources),
            'destinations' => implode(';', $destinations),
            'annotations' => 'duration,distance',
        ];

        return Cache::remember('osrm:'.md5($coordinates.http_build_query($query)), now()->addDay(), function () use ($coordinates, $query) {
            try {
                $response = Http::timeout(12)->acceptJson()->get("{$this->baseUrl}/table/v1/driving/{$coordinates}", $query);
            } catch (ConnectionException) {
                throw new ApiUnavailable('Le calcul des itinéraires ne répond pas. Réessayez dans un instant.');
            }

            if ($response->failed() || $response->json('code') !== 'Ok') {
                throw new ApiUnavailable('Le calcul des itinéraires est indisponible pour le moment (service très sollicité). Réessayez dans une minute.');
            }

            return ['durations' => $response->json('durations'), 'distances' => $response->json('distances')];
        });
    }
}

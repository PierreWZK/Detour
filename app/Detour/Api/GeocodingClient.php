<?php

namespace App\Detour\Api;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Adresses : service de géocodage de la Géoplateforme de l'IGN (ex-API Adresse de data.gouv.fr), sans clé.
 * Les réponses sont gardées une journée en cache.
 */
final class GeocodingClient
{
    private const URL = 'https://data.geopf.fr/geocodage/search';

    /** @return list<array{label: string, lat: float, lon: float}> */
    public function search(string $query, int $limit = 5): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 3) {
            return [];
        }

        return Cache::remember('adresse:'.$limit.':'.mb_strtolower($query), now()->addDay(), function () use ($query, $limit) {
            try {
                $response = Http::timeout(6)->acceptJson()->get(self::URL, ['q' => $query, 'limit' => $limit, 'autocomplete' => 1]);
            } catch (ConnectionException) {
                throw new ApiUnavailable('Le service des adresses ne répond pas. Essayez « Ma position », ou réessayez dans un instant.');
            }

            if ($response->failed()) {
                throw new ApiUnavailable('Le service des adresses est indisponible pour le moment.');
            }

            return array_map(fn (array $f) => [
                'label' => (string) $f['properties']['label'],
                'lat' => (float) $f['geometry']['coordinates'][1],
                'lon' => (float) $f['geometry']['coordinates'][0],
            ], $response->json('features') ?? []);
        });
    }
}

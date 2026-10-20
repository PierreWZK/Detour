<?php

namespace App\Detour\Api;

use App\Detour\Fuel;
use App\Detour\Station;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Prix des carburants du jour : flux instantané du ministère de l'Économie (data.economie.gouv.fr), sans clé.
 * Les réponses sont gardées 10 minutes en cache.
 */
final class FuelPriceClient
{
    private const URL = 'https://data.economie.gouv.fr/api/explore/v2.1/catalog/datasets/prix-des-carburants-en-france-flux-instantane-v2/records';

    /** L'API ne renvoie pas plus de 100 stations par requête */
    private const LIMIT = 100;

    /**
     * Les stations qui vendent ce carburant (et ne sont pas en rupture) dans un rayon autour d'un point,
     * des moins chères aux plus chères.
     *
     * @return list<Station>
     */
    public function around(float $lat, float $lon, float $radiusKm, Fuel $fuel): array
    {
        $key = sprintf('prix:%s:%.3f:%.3f:%d', $fuel->value, $lat, $lon, (int) ceil($radiusKm));

        // On garde en cache les données brutes de l'API (pas d'objets PHP : Laravel refuse de les relire)
        $rows = Cache::remember($key, now()->addMinutes(10), function () use ($lat, $lon, $radiusKm, $fuel) {
            $where = sprintf(
                "within_distance(geom, geom'POINT(%F %F)', %Fkm) AND %s_prix is not null AND %s_rupture_type is null",
                $lon, $lat, $radiusKm, $fuel->value, $fuel->value,
            );

            try {
                $response = Http::timeout(10)->acceptJson()->get(self::URL, [
                    'where' => $where,
                    'order_by' => $fuel->value.'_prix asc',
                    'limit' => self::LIMIT,
                ]);
            } catch (ConnectionException) {
                throw new ApiUnavailable('Le service des prix des carburants ne répond pas. Réessayez dans un instant.');
            }

            if ($response->failed()) {
                throw new ApiUnavailable('Le service des prix des carburants est indisponible pour le moment.');
            }

            return $response->json('results') ?? [];
        });

        return array_map($this->station(...), $rows);
    }

    /** @param  array<string, mixed>  $row */
    private function station(array $row): Station
    {
        $prices = [];
        foreach (Fuel::cases() as $fuel) {
            $price = $row[$fuel->value.'_prix'] ?? null;
            $updated = $row[$fuel->value.'_maj'] ?? null;
            if ($price !== null && $updated !== null && ($row[$fuel->value.'_rupture_type'] ?? null) === null) {
                $prices[$fuel->value] = ['price' => (float) $price, 'updated' => CarbonImmutable::parse($updated)];
            }
        }

        return new Station(
            id: (string) $row['id'],
            address: (string) ($row['adresse'] ?? ''),
            city: (string) ($row['ville'] ?? ''),
            postcode: (string) ($row['cp'] ?? ''),
            lat: (float) $row['geom']['lat'],
            lon: (float) $row['geom']['lon'],
            highway: ($row['pop'] ?? '') === 'A',
            prices: $prices,
            automate24: ($row['horaires_automate_24_24'] ?? '') === 'Oui',
            services: array_values($row['services_service'] ?? []),
            hours: $this->hours($row['horaires'] ?? null),
        );
    }

    /**
     * Horaires par jour, à partir du JSON du flux : « 06:00–12:00 », « 06:00–12:00, 14:00–19:00 », « Fermé », ou « » si non renseigné.
     *
     * @return array<string, string>
     */
    private function hours(?string $json): array
    {
        $days = json_decode($json ?? '', true)['jour'] ?? [];
        $hours = [];
        foreach ($days as $day) {
            $name = $day['@nom'] ?? null;
            if ($name === null) {
                continue;
            }
            if (($day['@ferme'] ?? '') === '1') {
                $hours[$name] = 'Fermé';

                continue;
            }
            $slots = $day['horaire'] ?? [];
            $slots = isset($slots['@ouverture']) ? [$slots] : $slots;
            $hours[$name] = implode(', ', array_map(
                fn ($s) => str_replace('.', ':', $s['@ouverture'] ?? '').'–'.str_replace('.', ':', $s['@fermeture'] ?? ''),
                $slots,
            ));
        }

        return $hours;
    }
}

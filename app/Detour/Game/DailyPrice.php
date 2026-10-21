<?php

namespace App\Detour\Game;

use App\Detour\Api\ApiUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * « Le juste prix » : deviner le prix moyen du gazole en France aujourd'hui.
 * La réponse est la moyenne des stations mises à jour depuis 48 h, figée au premier calcul de la journée
 * (heure de Paris) : tout le monde joue avec la même.
 */
final class DailyPrice
{
    private const URL = 'https://data.economie.gouv.fr/api/explore/v2.1/catalog/datasets/prix-des-carburants-en-france-flux-instantane-v2/records';

    /** Écarts (en centimes) et appréciations, du plus précis au moins précis */
    private const GRADES = [
        [0.5, '🎯', 'Dans le mille'],
        [2, '🔥', 'Brûlant'],
        [5, '♨️', 'Chaud'],
        [10, '🙂', 'Tiède'],
        [INF, '🧊', 'Froid'],
    ];

    /** @return array{date: string, average: float, min: float, max: float, count: int} */
    public function today(?CarbonImmutable $now = null): array
    {
        $today = ($now ?? CarbonImmutable::now('Europe/Paris'))->setTimezone('Europe/Paris');
        $date = $today->toDateString();

        // Gardée jusqu'au lendemain : c'est la réponse de la journée, la même pour tous
        return Cache::remember('juste-prix:'.$date, $today->endOfDay(), function () use ($today, $date) {
            try {
                $response = Http::timeout(10)->acceptJson()->get(self::URL, [
                    'select' => 'avg(gazole_prix) as moyenne, min(gazole_prix) as mini, max(gazole_prix) as maxi, count(*) as n',
                    'where' => sprintf("gazole_prix is not null AND gazole_maj >= date'%s'", $today->subDays(2)->toDateString()),
                ]);
            } catch (ConnectionException) {
                throw new ApiUnavailable('Le service des prix ne répond pas : le juste prix revient dans un instant.');
            }

            $row = $response->json('results.0');
            if ($response->failed() || ! isset($row['moyenne'])) {
                throw new ApiUnavailable('Le prix du jour est indisponible pour le moment.');
            }

            return [
                'date' => $date,
                'average' => round((float) $row['moyenne'], 3),
                'min' => (float) $row['mini'],
                'max' => (float) $row['maxi'],
                'count' => (int) $row['n'],
            ];
        });
    }

    /**
     * La note d'une proposition : l'écart en centimes et son appréciation.
     *
     * @return array{diffCents: float, emoji: string, label: string, direction: string}
     */
    public static function grade(float $guess, float $answer): array
    {
        $diff = round(abs($guess - $answer) * 100, 1);
        foreach (self::GRADES as [$limit, $emoji, $label]) {
            if ($diff <= $limit) {
                break;
            }
        }

        return [
            'diffCents' => $diff,
            'emoji' => $emoji,
            'label' => $label,
            'direction' => $diff == 0.0 ? 'exact' : ($guess > $answer ? 'trop haut' : 'trop bas'),
        ];
    }
}

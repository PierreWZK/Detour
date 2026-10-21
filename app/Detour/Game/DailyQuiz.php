<?php

namespace App\Detour\Game;

use Carbon\CarbonImmutable;

/**
 * « Quiz auto » : une question par jour sur la voiture, la conduite et le carburant, la même pour tout le monde.
 * Les questions sont parcourues dans un ordre mélangé une fois pour toutes : pas de répétition avant la fin de la liste.
 */
final class DailyQuiz
{
    /** @var list<array{id: string, q: string, choices: list<string>, answer: int, explain: string}> */
    private array $questions;

    public function __construct(?array $questions = null)
    {
        $this->questions = $questions ?? require __DIR__.'/questions.php';
    }

    /** @return array{id: string, q: string, choices: list<string>, answer: int, explain: string} */
    public function questionOf(CarbonImmutable $day): array
    {
        $order = $this->order(count($this->questions));
        // Numéro du jour (heure de Paris) depuis le 1er janvier 1970
        $dayNumber = intdiv(CarbonImmutable::parse($day->setTimezone('Europe/Paris')->toDateString(), 'UTC')->getTimestamp(), 86400);

        return $this->questions[$order[$dayNumber % count($order)]];
    }

    /** @return list<array{id: string, q: string, choices: list<string>, answer: int, explain: string}> */
    public function all(): array
    {
        return $this->questions;
    }

    /** Mélange déterministe (même graine pour tous) : l'ordre ne change pas d'un jour à l'autre */
    private function order(int $count): array
    {
        $order = range(0, $count - 1);
        $seed = 8;
        for ($i = $count - 1; $i > 0; $i--) {
            $seed = ($seed * 1103515245 + 12345) % 2147483648;
            $j = $seed % ($i + 1);
            [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
        }

        return $order;
    }
}

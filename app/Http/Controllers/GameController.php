<?php

namespace App\Http\Controllers;

use App\Detour\Api\ApiUnavailable;
use App\Detour\Game\DailyPrice;
use App\Detour\Game\DailyQuiz;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Les jeux du jour. Les réponses restent sur le serveur : la page ne les contient pas,
 * on les obtient en jouant (une tentative par jour, retenue sur l'appareil).
 */
class GameController extends Controller
{
    public function __construct(
        private readonly DailyQuiz $quiz,
    ) {}

    public function index(): View
    {
        $today = CarbonImmutable::now('Europe/Paris');
        $question = $this->quiz->questionOf($today);

        return view('game', [
            'date' => $today->toDateString(),
            'question' => ['id' => $question['id'], 'q' => $question['q'], 'choices' => $question['choices']],
            // Indice : la moyenne d'hier, si quelqu'un a joué hier
            'yesterday' => Cache::get('juste-prix:'.$today->subDay()->toDateString())['average'] ?? null,
        ]);
    }

    /** « Le juste prix » : la proposition est notée d'après la moyenne nationale du jour */
    public function price(Request $request, DailyPrice $price): JsonResponse
    {
        $guess = (float) $request->validate(['guess' => ['required', 'numeric', 'between:0.5,5']])['guess'];

        try {
            $day = $price->today();
        } catch (ApiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json([
            'date' => $day['date'],
            'guess' => round($guess, 3),
            'answer' => $day['average'],
            'min' => $day['min'],
            'max' => $day['max'],
            'count' => $day['count'],
            'grade' => DailyPrice::grade($guess, $day['average']),
        ]);
    }

    /** « Quiz auto » : la réponse et l'explication */
    public function quiz(Request $request): JsonResponse
    {
        $choice = (int) $request->validate(['choice' => ['required', 'integer', 'between:0,3']])['choice'];
        $today = CarbonImmutable::now('Europe/Paris');
        $question = $this->quiz->questionOf($today);

        return response()->json([
            'date' => $today->toDateString(),
            'id' => $question['id'],
            'choice' => $choice,
            'correct' => $choice === $question['answer'],
            'answer' => $question['answer'],
            'explain' => $question['explain'],
        ]);
    }
}

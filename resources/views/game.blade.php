@extends('layouts.app')

@section('title', 'Jeu du jour · Détour')

@php
    $day = \Carbon\CarbonImmutable::parse($date)->locale('fr')->isoFormat('dddd D MMMM');
@endphp

@section('content')
    <section class="container game-page" data-game data-date="{{ $date }}">
        <div class="section-head">
            <p class="eyebrow">Jeu du jour · {{ $day }}</p>
            <h1 class="game-title">Deux défis par jour</h1>
            <p class="lead">Le même pour tout le monde, une seule tentative chacun. Revenez demain pour garder votre série 🔥.</p>
        </div>

        <div class="games">
            {{-- Le juste prix --}}
            <article class="card game" data-game-price>
                <div class="game-head">
                    <h2>⛽ Le juste prix</h2>
                    <span class="streak" data-streak="prix" hidden></span>
                </div>
                <p>Quel est aujourd'hui le <strong>prix moyen du gazole</strong> en France ?</p>
                <p class="muted small">Moyenne de toutes les stations qui ont mis leur prix à jour ces deux derniers jours.
                    @if ($yesterday) Indice : hier, c'était {{ number_format($yesterday, 3, ',', '') }} €/L. @endif
                </p>

                <form class="guess" data-form>
                    <label for="guess" class="sr-only">Votre estimation, en euros par litre</label>
                    <div class="guess-value"><output data-output>2,000</output><span> €/L</span></div>
                    <input id="guess" name="guess" type="range" min="1.500" max="3.000" step="0.001" value="2.000" data-range>
                    <div class="guess-nudge">
                        <button type="button" class="btn ghost small" data-nudge="-0.01">−1 ct</button>
                        <button type="button" class="btn ghost small" data-nudge="-0.001">−0,1</button>
                        <button type="button" class="btn ghost small" data-nudge="0.001">+0,1</button>
                        <button type="button" class="btn ghost small" data-nudge="0.01">+1 ct</button>
                    </div>
                    <button class="btn wide">Valider mon prix</button>
                </form>

                <div class="game-result" data-result hidden></div>
            </article>

            {{-- Quiz auto --}}
            <article class="card game" data-game-quiz>
                <div class="game-head">
                    <h2>🚗 Quiz auto</h2>
                    <span class="streak" data-streak="quiz" hidden></span>
                </div>
                <p class="quiz-question">{{ $question['q'] }}</p>
                <div class="choices" data-choices>
                    @foreach ($question['choices'] as $i => $choice)
                        <button type="button" class="choice" data-choice="{{ $i }}"><span class="letter">{{ ['A', 'B', 'C', 'D'][$i] }}</span>{{ $choice }}</button>
                    @endforeach
                </div>
                <div class="game-result" data-result hidden></div>
            </article>
        </div>

        <div class="card share" data-share hidden>
            <p><strong>Votre journée</strong> <span class="muted" data-share-text></span></p>
            <button type="button" class="btn small" data-share-button>Partager mon résultat</button>
        </div>

        <p class="muted small">Vos réponses et vos séries restent sur cet appareil. Prix : flux officiel du ministère de l'Économie.</p>
    </section>

    @vite('resources/js/game.js')
@endsection

@extends('layouts.app')

@php
    use App\Support\Format;
    $best = collect($stations)->sortByDesc(fn ($s) => $s['gain']->net)->first();
@endphp

@section('content')
    {{-- En-tête : la promesse et un verdict d'exemple --}}
    <section class="hero container">
        <div class="hero-text">
            <p class="eyebrow">Carburant · Gain net · Open data</p>
            <h1>Le plein moins cher, <span class="mark">détour compris.</span></h1>
            <p class="lead">
                Les applis de carburant montrent la station la moins chère, sans dire si le détour vaut le coup.
                Détour calcule le <strong>gain net</strong> de chaque station : l'économie sur le plein, moins le carburant
                brûlé pour y aller.
            </p>
            <div class="row">
                <a href="{{ route('search') }}" class="btn">Trouver ma station</a>
                <a href="#calcul" class="btn ghost">Comment on calcule</a>
            </div>
            <ul class="trust">
                <li>✓ Prix officiels du jour</li>
                <li>✓ Itinéraires réels</li>
                <li>✓ Sans compte, gratuit</li>
            </ul>
        </div>

        <aside class="verdict card" aria-label="Exemple de verdict">
            <p class="verdict-tag">Exemple de verdict</p>
            <p class="verdict-line">Allez à <strong>la station du centre</strong></p>
            <p class="verdict-gain">{{ Format::signedEuros($hero->net) }} <span>net</span></p>
            <p class="verdict-sub">pour 4 minutes et 3 km de détour</p>
            <dl class="verdict-detail">
                <div><dt>Économie sur 40 L (5 centimes de moins)</dt><dd class="good">{{ Format::signedEuros($hero->saving) }}</dd></div>
                <div><dt>Carburant du détour ({{ number_format($hero->burnedLiters, 2, ',', '') }} L)</dt><dd class="bad">{{ Format::signedEuros(-$hero->fuelCost) }}</dd></div>
                <div class="total"><dt>Gain net</dt><dd>{{ Format::signedEuros($hero->net) }}</dd></div>
            </dl>
            <p class="muted small">Rentable dès {{ $hero->breakEvenLiters }} litres.</p>
        </aside>
    </section>

    {{-- Le problème : la moins chère n'est pas toujours la meilleure --}}
    <section class="band container" id="probleme">
        <div class="section-head">
            <p class="eyebrow">Le piège</p>
            <h2>La moins chère n'est pas toujours la bonne affaire</h2>
            <p class="lead">
                Un plein de {{ $liters }} litres, une voiture qui consomme {{ number_format($consumption, 1, ',', '') }} L/100 km,
                et trois stations. Celle à 8 centimes de moins est à 18 km aller-retour : elle rapporte moins que celle à deux rues.
            </p>
        </div>

        <div class="table-wrap">
            <table class="compare">
                <thead>
                    <tr>
                        <th scope="col">Station</th>
                        <th scope="col">Prix</th>
                        <th scope="col">Détour</th>
                        <th scope="col">Gain net</th>
                        <th scope="col">Avec votre temps <span class="muted">({{ $hourValue }} €/h)</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stations as $station)
                        <tr @class(['best' => $station === $best])>
                            <th scope="row">
                                {{ $station['name'] }}
                                <span class="muted small">{{ $station['hint'] }}</span>
                                @if ($station === $best)<span class="badge">Le meilleur choix</span>@endif
                            </th>
                            <td>{{ Format::pricePerLiter($station['price']) }}</td>
                            <td>{{ $station['km'] > 0 ? Format::km($station['km']).' · '.$station['minutes'].' min' : '—' }}</td>
                            <td @class(['good' => $station['gain']->net > 0, 'bad' => $station['gain']->net < 0])>
                                {{ $station['km'] > 0 ? Format::signedEuros($station['gain']->net) : 'Référence' }}
                            </td>
                            <td @class(['good' => $station['gainWithTime']->net > 0, 'bad' => $station['gainWithTime']->net < 0])>
                                {{ $station['km'] > 0 ? Format::signedEuros($station['gainWithTime']->net) : 'Référence' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="muted small">Exemple calculé avec la même formule que l'outil. Le gain est mesuré par rapport à la station sur votre route ({{ Format::pricePerLiter($referencePrice) }}).</p>
    </section>

    {{-- Comment ça marche --}}
    <section class="band container" id="comment">
        <div class="section-head">
            <p class="eyebrow">Comment ça marche</p>
            <h2>Trois questions, un verdict</h2>
        </div>
        <ol class="steps">
            <li class="card step">
                <span class="step-num">1</span>
                <span class="step-icon" aria-hidden="true">📍</span>
                <h3>Où êtes-vous ?</h3>
                <p class="muted">Votre position ou une adresse. Ajoutez une destination si vous êtes en route : le détour est calculé sur votre trajet.</p>
            </li>
            <li class="card step">
                <span class="step-num">2</span>
                <span class="step-icon" aria-hidden="true">⛽</span>
                <h3>Votre plein</h3>
                <p class="muted">Le carburant (gazole, SP95-E10, SP98, E85, GPL), les litres à mettre et ce que consomme votre voiture.</p>
            </li>
            <li class="card step">
                <span class="step-num">3</span>
                <span class="step-icon" aria-hidden="true">✅</span>
                <h3>Le verdict</h3>
                <p class="muted">Les stations classées par gain net, sur une carte, avec le détour en kilomètres et en minutes, et l'itinéraire en un clic.</p>
            </li>
        </ol>
    </section>

    {{-- La formule --}}
    <section class="band container split" id="calcul">
        <div class="section-head">
            <p class="eyebrow">Le calcul</p>
            <h2>Une formule simple, sans surprise</h2>
            <p class="lead">Le gain se mesure par rapport à la station de référence : la moins chère <strong>sur votre trajet</strong> (à moins de 500 m), à défaut la plus proche.</p>
            <ul class="facts">
                <li><strong>Sans destination</strong> : le détour est l'aller-retour depuis votre point de départ.</li>
                <li><strong>Avec destination</strong> : détour = (départ → station → arrivée) − (départ → arrivée).</li>
                <li><strong>Votre temps compte ?</strong> Indiquez ce que vaut une heure : le temps du détour est retiré du gain.</li>
                <li><strong>Seuil de rentabilité</strong> : « ce détour devient rentable à partir de 26 litres ».</li>
            </ul>
        </div>
        <div class="formula card">
            <p class="formula-line"><span class="good">Économie</span> = (prix de référence − prix de la station) × litres</p>
            <p class="formula-line"><span class="bad">Carburant du détour</span> = km de détour × consommation ÷ 100 × prix de la station</p>
            <p class="formula-line"><span class="bad">Temps</span> <span class="muted">(facultatif)</span> = minutes de détour ÷ 60 × valeur de votre heure</p>
            <p class="formula-total">Gain net = économie − carburant du détour − temps</p>
        </div>
    </section>

    {{-- Les données --}}
    <section class="band container" id="donnees">
        <div class="section-head">
            <p class="eyebrow">Les données</p>
            <h2>Des sources publiques, sans compte ni clé</h2>
        </div>
        <ul class="sources">
            <li class="card">
                <h3>⛽ Prix des carburants</h3>
                <p class="muted">Le flux officiel du ministère de l'Économie (data.economie.gouv.fr), mis à jour en continu par les stations. Les prix de plus de 48 h sont signalés, les ruptures écartées.</p>
            </li>
            <li class="card">
                <h3>🗺️ Itinéraires</h3>
                <p class="muted">Distances et durées réelles par la route (OSRM), calculées en une seule requête pour toutes les stations autour de vous.</p>
            </li>
            <li class="card">
                <h3>📍 Adresses et carte</h3>
                <p class="muted">La Base Adresse Nationale pour trouver une adresse, OpenStreetMap et Leaflet pour la carte.</p>
            </li>
        </ul>
    </section>

    {{-- Le jeu du jour --}}
    <section class="band container game-teaser" id="jeu">
        <div class="section-head">
            <p class="eyebrow">Jeu du jour</p>
            <h2>Deux défis par jour, même sans faire le plein</h2>
            <p class="lead">Le même pour tout le monde, une tentative chacun, une série 🔥 à faire durer. Sans compte.</p>
            <a href="{{ route('game') }}" class="btn">🔥 Jouer au jeu du jour</a>
        </div>
        <div class="teaser-cards">
            <a href="{{ route('game') }}" class="card">
                <span class="teaser-icon" aria-hidden="true">⛽</span>
                <div><h3>Le juste prix</h3><p class="muted">Devinez le prix moyen du gazole en France aujourd'hui, au centime près.</p></div>
            </a>
            <a href="{{ route('game') }}" class="card">
                <span class="teaser-icon" aria-hidden="true">🚗</span>
                <div><h3>Quiz auto</h3><p class="muted">Une question sur la voiture, la conduite ou le carburant, et l'explication.</p></div>
            </a>
        </div>
    </section>

    {{-- Appel final --}}
    <section class="band container">
        <div class="cta card">
            <div>
                <p class="eyebrow">À vous</p>
                <h2>Où faire le plein aujourd'hui ?</h2>
                <p class="lead">Votre position, votre plein, et le verdict en quelques secondes. Sans compte, sans application à installer.</p>
            </div>
            <a href="{{ route('search') }}" class="btn">Trouver ma station</a>
        </div>
    </section>
@endsection

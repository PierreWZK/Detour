@extends('layouts.app')

@section('title', 'Trouver ma station · Détour')

@php
    use App\Detour\Fuel;
    use App\Support\Format;

    $query = $result?->query;
    $best = $result?->best();
    // La référence est « sur le trajet » si son détour ne dépasse pas 1 km, sinon c'est la plus proche
    $referencePlace = $result?->reference && $result->reference->detourKm <= App\Detour\Search\DetourSearch::ON_ROUTE_KM
        ? 'sur votre trajet' : 'à la station la plus proche';
    $fuel = Fuel::from($form['carburant']);

    // Lien d'itinéraire (Google Maps, qui s'ouvre aussi dans l'application sur mobile)
    $directions = function ($candidate) use ($query) {
        $point = fn ($lat, $lon) => sprintf('%.6F,%.6F', $lat, $lon);
        $params = ['api' => 1, 'origin' => $point($query->start->lat, $query->start->lon), 'travelmode' => 'driving'];
        if ($query->destination) {
            $params['destination'] = $point($query->destination->lat, $query->destination->lon);
            $params['waypoints'] = $point($candidate->station->lat, $candidate->station->lon);
        } else {
            $params['destination'] = $point($candidate->station->lat, $candidate->station->lon);
        }

        return 'https://www.google.com/maps/dir/?'.http_build_query($params);
    };

    $map = $result && $result->candidates ? [
        'start' => ['lat' => $query->start->lat, 'lon' => $query->start->lon, 'label' => $query->start->label],
        'destination' => $query->destination ? ['lat' => $query->destination->lat, 'lon' => $query->destination->lon, 'label' => $query->destination->label] : null,
        'stations' => array_map(fn ($c) => [
            'id' => $c->station->id,
            'lat' => $c->station->lat,
            'lon' => $c->station->lon,
            'label' => $c->station->label(),
            'price' => Format::pricePerLiter($c->price),
            'gain' => $c->isReference ? 'Référence' : Format::signedEuros($c->gain->net),
            'detour' => $c->detourKm > 0 ? Format::km($c->detourKm).' · '.(int) $c->detourMinutes.' min' : 'Sur le trajet',
            'kind' => $c === $best && $result->detourWorthIt() ? 'best' : ($c->isReference ? 'reference' : ($c->gain->net > 0 ? 'good' : 'bad')),
        ], $result->candidates),
    ] : null;
@endphp

@section('content')
    <div class="search container">
        {{-- Le formulaire : où, et quel plein --}}
        {{-- L'ancre #resultats amène directement au verdict après la recherche (utile sur mobile) --}}
        <form class="card search-form" method="GET" action="{{ route('search') }}#resultats" data-search-form>
            <h1 class="search-title">Trouver ma station</h1>

            <fieldset>
                <legend>Le trajet</legend>
                <div class="field address" data-address>
                    <label for="depart">Départ</label>
                    <div class="address-input">
                        <input id="depart" name="depart" value="{{ $form['depart'] ?? '' }}" placeholder="Une adresse, une ville…" autocomplete="off" required>
                        <button type="button" class="btn ghost small" data-locate title="Utiliser ma position">📍 Ma position</button>
                    </div>
                    <input type="hidden" name="depart_lat" value="{{ $form['depart_lat'] ?? '' }}">
                    <input type="hidden" name="depart_lon" value="{{ $form['depart_lon'] ?? '' }}">
                    <ul class="suggestions" role="listbox" hidden></ul>
                </div>
                <div class="field address" data-address>
                    <label for="arrivee">Destination <span class="muted">(facultatif)</span></label>
                    <input id="arrivee" name="arrivee" value="{{ $form['arrivee'] ?? '' }}" placeholder="Sans destination : aller-retour" autocomplete="off">
                    <input type="hidden" name="arrivee_lat" value="{{ $form['arrivee_lat'] ?? '' }}">
                    <input type="hidden" name="arrivee_lon" value="{{ $form['arrivee_lon'] ?? '' }}">
                    <ul class="suggestions" role="listbox" hidden></ul>
                </div>
            </fieldset>

            <fieldset>
                <legend>Votre plein</legend>
                <div class="fuels" role="radiogroup" aria-label="Carburant">
                    @foreach ($fuels as $option)
                        <label class="fuel">
                            <input type="radio" name="carburant" value="{{ $option->value }}" @checked($option === $fuel) data-profile>
                            <span>{{ $option->label() }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="litres">Litres à mettre</label>
                        <input id="litres" name="litres" type="number" inputmode="decimal" min="5" max="150" step="1" value="{{ $form['litres'] }}" data-profile>
                    </div>
                    <div class="field">
                        <label for="conso">Consommation <span class="muted">(L/100 km)</span></label>
                        <input id="conso" name="conso" type="number" inputmode="decimal" min="2" max="30" step="0.1" value="{{ $form['conso'] }}" data-profile>
                    </div>
                </div>
                <div class="field">
                    <label for="heure">Mon temps vaut <span class="muted">(€/h, 0 pour l'ignorer)</span></label>
                    <input id="heure" name="heure" type="number" inputmode="decimal" min="0" max="200" step="1" value="{{ $form['heure'] }}" data-profile>
                </div>
            </fieldset>

            @if ($errors->any())
                <p class="error" role="alert">{{ $errors->first() }}</p>
            @endif
            <button class="btn wide">Calculer le meilleur plein</button>
            <p class="muted small">Votre carburant et votre consommation sont retenus sur cet appareil.</p>
        </form>

        {{-- Les résultats --}}
        <section class="results" id="resultats" aria-live="polite">
            @if ($error)
                <div class="card notice bad-notice" role="alert">
                    <strong>Impossible de calculer pour le moment</strong>
                    <p>{{ $error }}</p>
                </div>
            @elseif (! $result)
                <div class="card notice">
                    <strong>Où faire le plein ?</strong>
                    <p class="muted">Indiquez votre position ou une adresse, et éventuellement une destination. Détour compare les stations autour de vous et classe chacune par <strong>gain net</strong> : l'économie sur le plein, moins le carburant (et le temps) du détour.</p>
                </div>
            @elseif (! $result->candidates)
                <div class="card notice">
                    <strong>Aucune station trouvée</strong>
                    <p class="muted">Aucune station ne vend de {{ $fuel->label() }} dans ce secteur aujourd'hui. Essayez un autre carburant ou une autre adresse.</p>
                </div>
            @else
                {{-- Le verdict, en une phrase --}}
                <div @class(['card', 'verdict-box', 'is-detour' => $result->detourWorthIt()])>
                    @if ($result->detourWorthIt())
                        <p class="verdict-text">Allez à <strong>{{ $best->station->label() }}</strong> :
                            <span class="good">{{ Format::signedEuros($best->gain->net) }} net</span>
                            pour {{ (int) $best->detourMinutes }} minute{{ $best->detourMinutes >= 2 ? 's' : '' }} de détour.</p>
                        <p class="muted small">
                            {{ Format::pricePerLiter($best->price) }} contre {{ Format::pricePerLiter($result->reference->price) }} {{ $referencePlace }} ·
                            {{ Format::km($best->detourKm) }} de détour
                            @if ($best->gain->breakEvenLiters) · rentable dès {{ $best->gain->breakEvenLiters }} litres @endif
                        </p>
                    @else
                        <p class="verdict-text">Faites le plein à <strong>{{ $result->reference->station->label() }}</strong> : aucun détour ne rapporte plus.</p>
                        <p class="muted small">{{ Format::pricePerLiter($result->reference->price) }} · {{ $referencePlace }}</p>
                    @endif
                    <a class="btn small" href="{{ $directions($result->detourWorthIt() ? $best : $result->reference) }}" target="_blank" rel="noopener">Itinéraire ↗</a>
                </div>

                <div class="map card" id="map" aria-label="Carte des stations"></div>
                <script type="application/json" id="map-data">@json($map)</script>

                <p class="results-head">
                    <strong>{{ count($result->candidates) }} stations</strong> · {{ $fuel->label() }} · {{ (float) $form['litres'] }} L ·
                    {{ str_replace('.', ',', (string) (float) $form['conso']) }} L/100 km
                    @if ((float) $form['heure'] > 0) · temps à {{ (float) $form['heure'] }} €/h @endif
                    · {{ $query->destination ? 'trajet '.$query->start->label.' → '.$query->destination->label : 'aller-retour depuis '.$query->start->label }}
                </p>

                <ol class="stations">
                    @foreach ($result->candidates as $i => $c)
                        <li @class(['card', 'station', 'is-best' => $c === $best && $result->detourWorthIt(), 'is-reference' => $c->isReference]) data-station="{{ $c->station->id }}">
                            <div class="station-main">
                                <span class="station-rank">{{ $i + 1 }}</span>
                                <div class="station-name">
                                    <strong>{{ $c->station->label() }}</strong>
                                    <span class="tags">
                                        @if ($c === $best && $result->detourWorthIt())<span class="tag best">Le meilleur choix</span>@endif
                                        @if ($c->isReference)<span class="tag ref">Référence</span>@endif
                                        @if ($c->station->highway)<span class="tag">Autoroute</span>@endif
                                        @if ($c->station->automate24)<span class="tag">24/24</span>@endif
                                        @if ($c->isStale)<span class="tag stale" title="Prix mis à jour il y a plus de 48 h">Prix du {{ $c->station->updatedAt($fuel)?->format('d/m') }}</span>@endif
                                    </span>
                                </div>
                                <div class="station-figures">
                                    <span class="price">{{ Format::pricePerLiter($c->price) }}</span>
                                    <span class="muted small">{{ $c->detourKm > 0 ? Format::km($c->detourKm).' · '.(int) $c->detourMinutes.' min' : 'Sur le trajet' }}</span>
                                </div>
                                <div @class(['station-gain', 'good' => ! $c->isReference && $c->gain->net > 0, 'bad' => ! $c->isReference && $c->gain->net < 0])>
                                    {{ $c->isReference ? 'Référence' : Format::signedEuros($c->gain->net) }}
                                </div>
                            </div>

                            <details class="station-sheet">
                                <summary>Détail et fiche de la station</summary>
                                <div class="sheet-grid">
                                    @unless ($c->isReference)
                                        <dl class="breakdown">
                                            <div><dt>Économie sur {{ (float) $form['litres'] }} L</dt><dd>{{ Format::signedEuros($c->gain->saving) }}</dd></div>
                                            <div><dt>Carburant du détour ({{ number_format($c->gain->burnedLiters, 2, ',', '') }} L)</dt><dd>{{ Format::signedEuros(-$c->gain->fuelCost) }}</dd></div>
                                            @if ($c->gain->timeCost > 0)
                                                <div><dt>Temps ({{ (int) $c->detourMinutes }} min)</dt><dd>{{ Format::signedEuros(-$c->gain->timeCost) }}</dd></div>
                                            @endif
                                            <div class="total"><dt>Gain net</dt><dd>{{ Format::signedEuros($c->gain->net) }}</dd></div>
                                            <div><dt>Rentable dès</dt><dd>{{ $c->gain->breakEvenLiters !== null ? $c->gain->breakEvenLiters.' L' : 'jamais' }}</dd></div>
                                        </dl>
                                    @endunless
                                    <div>
                                        <h3>Prix</h3>
                                        <ul class="prices">
                                            @foreach ($fuels as $f)
                                                @if ($c->station->price($f) !== null)
                                                    <li @class(['current' => $f === $fuel])><span>{{ $f->label() }}</span> <strong>{{ Format::pricePerLiter($c->station->price($f)) }}</strong> <span class="muted small">{{ $c->station->updatedAt($f)->format('d/m H:i') }}</span></li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </div>
                                    @if (array_filter($c->station->hours))
                                        <div>
                                            <h3>Horaires</h3>
                                            <ul class="hours">
                                                @foreach ($c->station->hours as $day => $hours)
                                                    <li><span>{{ $day }}</span> <span class="muted">{{ $hours ?: '—' }}</span></li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                    @if ($c->station->services)
                                        <div>
                                            <h3>Services</h3>
                                            <p class="muted small">{{ implode(' · ', $c->station->services) }}</p>
                                        </div>
                                    @endif
                                </div>
                                <a class="btn ghost small" href="{{ $directions($c) }}" target="_blank" rel="noopener">Itinéraire vers cette station ↗</a>
                            </details>
                        </li>
                    @endforeach
                </ol>
                <p class="muted small">Prix : flux officiel du ministère de l'Économie · itinéraires : OSRM · carte : © contributeurs OpenStreetMap. Gain mesuré par rapport à la station de référence (la moins chère sur votre trajet, à défaut la plus proche).</p>
            @endif
        </section>
    </div>

    @vite('resources/js/search.js')
@endsection

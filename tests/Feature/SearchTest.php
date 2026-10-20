<?php

namespace Tests\Feature;

use App\Detour\Fuel;
use App\Detour\Search\DetourSearch;
use App\Detour\Search\Place;
use App\Detour\Search\SearchQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * La recherche de bout en bout, avec les trois API simulées.
 * Départ à Annecy ; trois stations reconnaissables à leur longitude, à une distance (aller) connue du départ.
 */
class SearchTest extends TestCase
{
    private const START = ['lat' => 45.9000, 'lon' => 6.1200];

    /** longitude => [id, adresse, prix du gazole, distance aller depuis le départ (km)] */
    private const STATIONS = [
        '6.12010' => ['R', 'Rue de la Route', 1.799, 0.3],   // sur le trajet : 0,6 km aller-retour
        '6.12020' => ['N', 'Avenue Proche', 1.759, 0.9],     // 1,8 km aller-retour
        '6.12030' => ['F', 'Zone Lointaine', 1.719, 9.0],    // 18 km aller-retour
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeApis();
    }

    public function test_la_page_sans_recherche_affiche_le_formulaire(): void
    {
        $this->get('/recherche')
            ->assertOk()
            ->assertSee('Trouver ma station')
            ->assertSee('Où faire le plein ?');
    }

    public function test_la_station_proche_passe_devant_la_moins_chere_trop_loin(): void
    {
        $response = $this->get('/recherche?'.http_build_query([
            'depart' => 'Annecy', 'depart_lat' => self::START['lat'], 'depart_lon' => self::START['lon'],
            'carburant' => 'gazole', 'litres' => 40, 'conso' => 6.5,
        ]));

        $response->assertOk()
            ->assertSee('Allez à')
            ->assertSee('Le meilleur choix')
            // Proche : 1,60 € d'économie − 0,117 L × 1,759 € = +1,39 € ; lointaine : 3,20 − 1,17 × 1,719 = +1,19 €
            ->assertSeeInOrder(['Avenue Proche', '+1,39', 'Zone Lointaine', '+1,19', 'Rue De La Route', 'Référence']);
    }

    public function test_le_temps_peut_rendre_le_detour_lointain_perdant(): void
    {
        $result = app(DetourSearch::class)->search(new SearchQuery(
            new Place(self::START['lat'], self::START['lon'], 'Annecy'), null, Fuel::Gazole, 40, 6.5, 15,
        ), CarbonImmutable::parse('2026-10-21 12:00'));

        $far = collect($result->candidates)->first(fn ($c) => $c->station->id === 'F');
        $this->assertLessThan(0, $far->gain->net);
        $this->assertSame('N', $result->best()->station->id);
        $this->assertSame('R', $result->reference->station->id);
    }

    public function test_un_prix_de_plus_de_48_heures_est_signale(): void
    {
        $result = app(DetourSearch::class)->search(new SearchQuery(
            new Place(self::START['lat'], self::START['lon'], 'Annecy'), null, Fuel::Gazole, 40, 6.5,
        ), CarbonImmutable::parse('2026-10-25 12:00'));

        $this->assertTrue($result->candidates[0]->isStale);
    }

    public function test_une_api_en_panne_donne_un_message_clair(): void
    {
        // On repart d'un client HTTP neuf : la simulation par défaut répondrait avant celle-ci
        Http::swap(new Factory);
        Http::fake(['data.economie.gouv.fr/*' => Http::response('Service Unavailable', 503)]);

        $this->get('/recherche?depart=Annecy&depart_lat=45.9&depart_lon=6.12')
            ->assertOk()
            ->assertSee('Impossible de calculer pour le moment')
            ->assertSee('Le service des prix des carburants est indisponible');
    }

    public function test_une_adresse_introuvable_est_signalee(): void
    {
        $this->get('/recherche?depart=zzzz')
            ->assertOk()
            ->assertSee('Adresse introuvable');
    }

    public function test_une_adresse_trouvee_lance_la_recherche(): void
    {
        $this->get('/recherche?depart=Annecy')
            ->assertOk()
            ->assertSee('Allez à');
    }

    public function test_un_trajet_trop_long_est_refuse(): void
    {
        $this->get('/recherche?depart=Annecy&depart_lat=45.9&depart_lon=6.12&arrivee=Paris&arrivee_lat=48.85&arrivee_lon=2.35')
            ->assertOk()
            ->assertSee('trajets de 150 km au plus');
    }

    public function test_les_suggestions_d_adresses(): void
    {
        $this->getJson('/adresses?q=Annecy')
            ->assertOk()
            ->assertJsonPath('0.label', '74000 Annecy');
    }

    private function fakeApis(): void
    {
        Http::fake([
            'data.economie.gouv.fr/*' => Http::response(['results' => array_map(fn ($lon, $s) => [
                'id' => $s[0],
                'adresse' => strtoupper($s[1]),
                'ville' => 'Annecy',
                'cp' => '74000',
                'pop' => 'R',
                'geom' => ['lat' => self::START['lat'], 'lon' => (float) $lon],
                'gazole_prix' => $s[2],
                'gazole_maj' => '2026-10-21T07:00:00+00:00',
                'gazole_rupture_type' => null,
                'horaires_automate_24_24' => 'Oui',
                'services_service' => ['Lavage'],
                'horaires' => null,
            ], array_keys(self::STATIONS), self::STATIONS)]),

            'data.geopf.fr/*' => fn (Request $request) => Http::response(['features' => str_contains($request->url(), 'zzzz') ? [] : [[
                'properties' => ['label' => '74000 Annecy'],
                'geometry' => ['coordinates' => [self::START['lon'], self::START['lat']]],
            ]]]),

            // OSRM : distance du départ (ou de l'arrivée) à chaque station, selon sa longitude ; 50 km/h de moyenne
            'router.project-osrm.org/*' => function (Request $request) {
                parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);
                preg_match('#/driving/([^?]+)#', $request->url(), $m);
                $points = array_map(fn ($p) => explode(',', $p), explode(';', $m[1]));
                $km = fn (array $p) => self::STATIONS[sprintf('%.5F', $p[0])][3] ?? 0.0;
                $leg = fn (array $a, array $b) => abs($km($a) - $km($b));

                $sources = explode(';', $query['sources']);
                $destinations = explode(';', $query['destinations']);
                $distances = array_map(fn ($s) => array_map(fn ($d) => $leg($points[$s], $points[$d]) * 1000, $destinations), $sources);
                $durations = array_map(fn ($row) => array_map(fn ($m) => $m / 1000 / 50 * 3600, $row), $distances);

                return Http::response(['code' => 'Ok', 'distances' => $distances, 'durations' => $durations]);
            },
        ]);
    }
}

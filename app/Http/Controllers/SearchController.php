<?php

namespace App\Http\Controllers;

use App\Detour\Api\ApiUnavailable;
use App\Detour\Api\GeocodingClient;
use App\Detour\Fuel;
use App\Detour\Search\DetourSearch;
use App\Detour\Search\Place;
use App\Detour\Search\SearchQuery;
use App\Detour\Search\TripTooLong;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SearchController extends Controller
{
    /** La France métropolitaine et la Corse, à peu près : au-delà, le flux des prix n'a pas de stations */
    private const BOUNDS = ['lat' => [41, 51.5], 'lon' => [-5.5, 10]];

    public function __construct(
        private readonly GeocodingClient $geocoding,
    ) {}

    /** La page de recherche : le formulaire, puis le verdict, la carte et la liste */
    public function index(Request $request, DetourSearch $search): View
    {
        $form = $request->validate([
            'depart' => ['nullable', 'string', 'max:200'],
            'depart_lat' => ['nullable', 'numeric', 'between:'.implode(',', self::BOUNDS['lat'])],
            'depart_lon' => ['nullable', 'numeric', 'between:'.implode(',', self::BOUNDS['lon'])],
            'arrivee' => ['nullable', 'string', 'max:200'],
            'arrivee_lat' => ['nullable', 'numeric', 'between:'.implode(',', self::BOUNDS['lat'])],
            'arrivee_lon' => ['nullable', 'numeric', 'between:'.implode(',', self::BOUNDS['lon'])],
            'carburant' => ['nullable', Rule::enum(Fuel::class)],
            'litres' => ['nullable', 'numeric', 'between:5,150'],
            'conso' => ['nullable', 'numeric', 'between:2,30'],
            'heure' => ['nullable', 'numeric', 'between:0,200'],
        ], [
            '*.between' => 'Valeur hors limites.',
            'depart_lat.between' => 'Détour ne couvre que la France métropolitaine.',
            'arrivee_lat.between' => 'Détour ne couvre que la France métropolitaine.',
        ]);

        $form += ['carburant' => Fuel::Gazole->value, 'litres' => 40, 'conso' => 6.5, 'heure' => 0];
        $view = ['form' => $form, 'fuels' => Fuel::cases(), 'result' => null, 'error' => null];

        if (blank($form['depart'] ?? null) && blank($form['depart_lat'] ?? null)) {
            return view('search', $view);
        }

        try {
            $start = $this->place($form['depart'] ?? null, $form['depart_lat'] ?? null, $form['depart_lon'] ?? null, 'Ma position');
            $destination = blank($form['arrivee'] ?? null) && blank($form['arrivee_lat'] ?? null)
                ? null
                : $this->place($form['arrivee'] ?? null, $form['arrivee_lat'] ?? null, $form['arrivee_lon'] ?? null, 'Destination');

            $view['result'] = $search->search(new SearchQuery(
                start: $start,
                destination: $destination,
                fuel: Fuel::from($form['carburant']),
                liters: (float) $form['litres'],
                consumption: (float) $form['conso'],
                hourValue: (float) $form['heure'],
            ));
        } catch (ApiUnavailable|TripTooLong $e) {
            $view['error'] = $e->getMessage();
        }

        return view('search', $view);
    }

    /** Suggestions d'adresses pendant la frappe */
    public function addresses(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');

        try {
            return response()->json($this->geocoding->search($query));
        } catch (ApiUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    /** Un point du trajet : ses coordonnées si on les a (position, suggestion choisie), sinon la première adresse trouvée */
    private function place(?string $label, mixed $lat, mixed $lon, string $default): Place
    {
        if (filled($lat) && filled($lon)) {
            return new Place((float) $lat, (float) $lon, filled($label) ? $label : $default);
        }

        $found = $this->geocoding->search((string) $label, 1)[0] ?? null;
        if ($found === null) {
            throw new ApiUnavailable("Adresse introuvable : « {$label} ». Essayez avec la ville ou le code postal.");
        }

        return new Place($found['lat'], $found['lon'], $found['label']);
    }
}

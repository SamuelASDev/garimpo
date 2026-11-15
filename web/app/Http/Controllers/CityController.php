<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Services\Locations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sugestões do filtro de cidade: "São Paulo — SP (54 vagas)" e "Todo o estado de São Paulo (SP)".
 * Só aparecem cidades que têm vaga no Garimpo.
 */
class CityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $key = Locations::key(mb_substr((string) $request->query('q'), 0, 60));
        if (mb_strlen($key) < 2) {
            return response()->json([]);
        }

        // A mesma cidade escrita de jeitos diferentes vira uma linha só (agrupada pela chave sem acento).
        $cities = JobPosting::query()
            ->whereNotNull('city_key')
            ->where(fn ($q) => $q->where('city_key', 'like', $key.'%')->orWhere('city_key', 'like', '% '.$key.'%'))
            ->selectRaw('city_key, max(city) as city, max(state) as state, count(*) as total')
            ->groupBy('city_key')->orderByDesc('total')->limit(8)->get()
            ->map(fn ($c) => ['type' => 'city', 'city' => $c->city, 'state' => $c->state, 'total' => (int) $c->total]);

        $states = collect(Locations::STATES)
            ->filter(fn ($name, $uf) => str_starts_with(Locations::key($name), $key) || strtolower($uf) === $key)
            ->map(fn ($name, $uf) => ['type' => 'state', 'city' => null, 'state' => $uf, 'name' => $name, 'total' => JobPosting::where('state', $uf)->count()])
            ->filter(fn ($s) => $s['total'] > 0)
            ->values();

        return response()->json($cities->concat($states)->values());
    }
}

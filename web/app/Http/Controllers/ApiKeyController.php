<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Chave da API na página Dev: uma por conta. Gerar outra substitui a atual (mostrada uma vez só). */
class ApiKeyController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('apiKey', ['name' => ['required', 'string', 'max:60']]);
        $user = $request->user();

        $replaced = $user->apiKeys()->exists();
        $user->apiKeys()->delete(); // uma chave por vez: a anterior para de funcionar na hora

        [, $plain] = ApiKey::generateFor($user, $data['name']);

        return redirect(route('dev').'#chaves-api')->with(['api_key_plain' => $plain, 'api_status' => $replaced ? 'Chave nova gerada. A anterior parou de funcionar.' : null]);
    }

    public function destroy(Request $request, ApiKey $apiKey): RedirectResponse
    {
        abort_unless($apiKey->user_id === $request->user()->id, 404);
        $apiKey->delete();

        return redirect(route('dev').'#chaves-api')->with('api_status', 'Chave revogada. Quem usava essa chave perde o acesso na hora.');
    }
}

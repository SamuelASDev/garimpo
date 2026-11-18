<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Chaves da API pública em "Minha conta": criar (mostrada uma vez só) e revogar. */
class ApiKeyController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('apiKey', ['name' => ['required', 'string', 'max:60']]);
        $user = $request->user();

        if ($user->apiKeys()->count() >= ApiKey::MAX_PER_USER) {
            return redirect(route('profile.edit').'#chaves-api')->with('api_error', 'Você já tem '.ApiKey::MAX_PER_USER.' chaves. Revogue uma para criar outra.');
        }

        [, $plain] = ApiKey::generateFor($user, $data['name']);

        return redirect(route('profile.edit').'#chaves-api')->with('api_key_plain', $plain);
    }

    public function destroy(Request $request, ApiKey $apiKey): RedirectResponse
    {
        abort_unless($apiKey->user_id === $request->user()->id, 404);
        $apiKey->delete();

        return redirect(route('profile.edit').'#chaves-api')->with('api_status', 'Chave revogada. Quem usava essa chave perde o acesso na hora.');
    }
}

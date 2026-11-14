<?php

namespace App\Http\Controllers;

use App\Models\Resume;
use App\Models\ResumeTailoring;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Direitos do titular (LGPD art. 18): consentimento da IA grátis e cópia de todos os dados.
 */
class PrivacyController extends Controller
{
    public function updateConsent(Request $request): RedirectResponse
    {
        $request->validate(['ai_consent' => ['required', 'boolean']]);
        $request->user()->forceFill(['ai_consent_at' => $request->boolean('ai_consent') ? now() : null])->save();

        return back()->with('status', $request->boolean('ai_consent')
            ? 'IA grátis ativada.'
            : 'IA grátis desligada: nada mais será enviado aos provedores de IA.');
    }

    /** Arquivo JSON com tudo o que o Garimpo guarda sobre a pessoa (acesso + portabilidade). */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $resume = $user->resume;

        $data = [
            'gerado_em' => now()->toIso8601String(),
            'conta' => [
                'nome' => $user->name,
                'email' => $user->email,
                'criada_em' => $user->created_at?->toIso8601String(),
                'termos_aceitos_em' => $user->terms_accepted_at?->toIso8601String(),
                'ia_gratis_autorizada_em' => $user->ai_consent_at?->toIso8601String(),
            ],
            'curriculo_principal' => $resume ? [
                'texto' => $resume->plainText(),
                'html' => $resume->html,
                'sugestoes_da_ia' => $resume->recommendations,
                'atualizado_em' => $resume->updated_at?->toIso8601String(),
            ] : null,
            'curriculos_por_vaga' => ResumeTailoring::with('jobPosting:id,title,company,url')->where('user_id', $user->id)->get()
                ->map(fn (ResumeTailoring $t): array => [
                    'vaga' => $t->jobPosting?->only(['title', 'company', 'url']),
                    'texto' => (new Resume(['html' => $t->content]))->plainText(),
                    'html' => $t->content,
                    'gerado_por' => $t->generated_by,
                    'atualizado_em' => $t->updated_at?->toIso8601String(),
                ]),
            'candidaturas' => $user->jobApplications()->get(['title', 'company', 'url', 'status', 'applied_at', 'last_contact_at', 'next_step', 'notes']),
            'conexoes_de_ia' => $user->tokens()->with('client:id,name')->get()->map(fn ($t): array => [
                'nome' => $t->name,
                'app' => $t->client?->name,
                'criada_em' => $t->created_at?->toIso8601String(),
                'expira_em' => $t->expires_at?->toIso8601String(),
                'revogada' => (bool) $t->revoked,
            ]),
        ];

        return response()->streamDownload(
            fn () => print(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'meus-dados-garimpo.json',
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }
}

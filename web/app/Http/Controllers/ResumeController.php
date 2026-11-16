<?php

namespace App\Http\Controllers;

use App\Models\Resume;
use App\Models\ResumeTailoring;
use App\Services\AiWriter;
use App\Services\AtsMatcher;
use App\Services\ResumeTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class ResumeController extends Controller
{
    /** Lista: currículo principal no topo + um currículo por vaga. Nenhum editor aberto aqui. */
    public function index(Request $request, AtsMatcher $matcher): View
    {
        $user = $request->user();
        $tailorings = ResumeTailoring::with('jobPosting')->where('user_id', $user->id)->latest('updated_at')->get()
            ->filter(fn (ResumeTailoring $t) => $t->jobPosting !== null)
            ->map(fn (ResumeTailoring $t) => [
                'tailoring' => $t,
                'job' => $t->jobPosting,
                'score' => $matcher->match(new Resume(['html' => $t->content]), $t->jobPosting)['score'],
                'pending' => count(ResumeTemplate::placeholders($t->content)),
            ]);

        return view('resume.index', [
            'resume' => $user->resume,
            'mainPending' => $user->resume?->html ? count(ResumeTemplate::placeholders($user->resume->html)) : 0,
            'tailorings' => $tailorings,
            'variants' => $user->resumeVariants()->latest('updated_at')->get(),
            'categories' => ResumeVariantController::CATEGORIES,
        ]);
    }

    public function edit(Request $request, ResumeTemplate $template): View
    {
        $resume = $request->user()->resume;
        $html = $resume?->html
            ?? ($resume ? $template->fromPlainText($resume->plainText()) : $template->render(ResumeTemplate::EXAMPLE));

        return view('resume.edit', ['html' => $html, 'isExample' => ! $resume, 'recommendations' => $resume?->recommendations ?? []]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['html' => ['required', 'string', 'max:200000']]);
        $request->user()->resume()->updateOrCreate([], ['html' => ResumeTemplate::sanitize($data['html'])]);

        return response()->json(['message' => 'Currículo salvo!']);
    }

    /** Recebe o texto extraído do PDF (no navegador) e devolve o currículo no modelo do site. */
    public function import(Request $request, AiWriter $writer, ResumeTemplate $template): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'min:50', 'max:60000']]);

        if (! $request->user()->hasAiConsent()) {
            return response()->json(['html' => $template->fromPlainText($data['text']), 'message' => 'Importei o texto sem formatar. Você ainda não autorizou a IA grátis. Ative em Minha conta → Privacidade (seu currículo vai sem telefone, e-mail e links).']);
        }

        $key = 'import:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json(['html' => $template->fromPlainText($data['text']), 'message' => 'Limite diário da IA grátis atingido: importei o texto sem formatar. Use "Conectar IA" para formatar com a sua IA.']);
        }

        try {
            $result = $writer->structureImported($data['text']);
            RateLimiter::hit($key, 86400);

            return response()->json(['html' => $template->render($result['data']), 'message' => 'Currículo importado e organizado no modelo. Revise os trechos em amarelo e salve.']);
        } catch (\RuntimeException $e) {
            return response()->json(['html' => $template->fromPlainText($data['text']), 'message' => $e->getMessage().' Importei o texto sem formatar.']);
        }
    }
}

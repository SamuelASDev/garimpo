<?php

namespace App\Http\Controllers;

use App\Services\AiWriter;
use App\Services\ResumeTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class ResumeController extends Controller
{
    public function edit(Request $request, ResumeTemplate $template): View
    {
        $resume = $request->user()->resume;
        $html = $resume?->html
            ?? ($resume ? $template->fromPlainText($resume->plainText()) : $template->render(ResumeTemplate::EXAMPLE));

        return view('resume.edit', ['html' => $html, 'isExample' => ! $resume]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['html' => ['required', 'string', 'max:200000']]);
        $request->user()->resume()->updateOrCreate([], ['html' => $data['html']]);

        return response()->json(['message' => 'Currículo salvo!']);
    }

    /** Recebe o texto extraído do PDF (no navegador) e devolve o currículo no modelo do site. */
    public function import(Request $request, AiWriter $writer, ResumeTemplate $template): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'min:50', 'max:60000']]);

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

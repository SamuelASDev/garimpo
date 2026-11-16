<?php

namespace App\Http\Controllers;

use App\Models\ResumeVariant;
use App\Services\ResumeTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Variações do currículo (ex.: "Back-end PHP", "Suporte N2"): a pessoa dá nome, categoria e descrição
 * e edita à vontade, sem precisar de uma vaga. O principal nunca é alterado.
 */
class ResumeVariantController extends Controller
{
    public const MAX_PER_USER = 20;

    /** Sugestões de categoria (a pessoa também pode escrever outra). */
    public const CATEGORIES = ['Back-end', 'Front-end', 'Full stack', 'Mobile', 'Dados', 'Infra/DevOps', 'Suporte', 'QA/Testes',
        'Segurança', 'UX/UI', 'Gestão/Produto', 'Estágio', 'Freelance', 'Internacional (inglês)'];

    public function store(Request $request, ResumeTemplate $template): RedirectResponse
    {
        $data = $request->validate($this->detailRules() + ['base' => ['required', 'in:principal,modelo']]);
        $user = $request->user();

        if ($user->resumeVariants()->count() >= self::MAX_PER_USER) {
            return back()->with('error', 'Você chegou ao limite de '.self::MAX_PER_USER.' variações. Exclua uma para criar outra.');
        }

        $main = $user->resume;
        $html = match (true) {
            $data['base'] === 'principal' && $main?->html !== null => $main->html,
            $data['base'] === 'principal' && $main !== null => $template->fromPlainText($main->plainText()),
            default => $template->render(ResumeTemplate::EXAMPLE),
        };

        $variant = $user->resumeVariants()->create([
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'html' => $html,
        ]);

        return redirect()->route('variants.edit', $variant)->with('status', 'Variação criada. Ajuste o que quiser: o currículo principal não muda.');
    }

    public function edit(Request $request, ResumeVariant $variant): View
    {
        $this->authorizeOwner($request, $variant);

        return view('resume.variant', ['variant' => $variant, 'categories' => self::CATEGORIES]);
    }

    /** Salva o documento (botão "Salvar" do editor). */
    public function update(Request $request, ResumeVariant $variant): JsonResponse
    {
        $this->authorizeOwner($request, $variant);
        $data = $request->validate(['html' => ['required', 'string', 'max:200000']]);
        $variant->update(['html' => ResumeTemplate::sanitize($data['html'])]);

        return response()->json(['message' => 'Variação salva!', 'placeholders' => ResumeTemplate::placeholders($variant->html)]);
    }

    /** Nome, categoria e descrição. */
    public function details(Request $request, ResumeVariant $variant): RedirectResponse
    {
        $this->authorizeOwner($request, $variant);
        $variant->update($request->validate($this->detailRules()));

        return back()->with('status', 'Dados da variação atualizados.');
    }

    public function destroy(Request $request, ResumeVariant $variant): RedirectResponse
    {
        $this->authorizeOwner($request, $variant);
        $variant->delete();

        return redirect()->route('resumes.index')->with('status', 'Variação excluída.');
    }

    /** @return array<string, list<string>> */
    private function detailRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:300'],
        ];
    }

    private function authorizeOwner(Request $request, ResumeVariant $variant): void
    {
        abort_unless($variant->user_id === $request->user()->id, 404);
    }
}

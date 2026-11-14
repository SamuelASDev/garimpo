<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <p class="text-sm text-gray-500 print:hidden"><a href="{{ route('resumes.index') }}" class="hover:text-gray-800">&larr; Currículos</a> · <a href="{{ route('jobs.show', $job) }}" class="hover:text-gray-800">ver a vaga</a></p>
        <h1 class="text-2xl font-bold text-gray-900 mt-1 print:hidden">Currículo para: {{ $job->title }}</h1>
        <p class="text-sm text-gray-500 print:hidden">
            {{ $job->company }} ·
            {{ $tailoring->generated_by === 'copia' ? 'cópia do seu currículo base' : 'adaptado por IA ('.$tailoring->generated_by.')' }}. Revise antes de enviar.
        </p>

        @if (session('error'))
            <div class="mt-4 p-3 rounded-lg bg-amber-50 text-amber-800 text-sm print:hidden">{{ session('error') }}</div>
        @endif

        <div class="mt-4">
            <x-resume-editor :config="[
                'html' => $tailoring->content,
                'saveUrl' => route('jobs.resume.update', $job),
                'wordUrl' => route('export.word'),
                'pdfUrl' => route('export.pdf'),
                'filename' => 'curriculo-'.$job->title,
                'match' => $match,
                'placeholders' => $placeholders,
                'recommendations' => $tailoring->recommendations ?? [],
                'assistantUrl' => route('jobs.resume.assistant', $job),
            ]">
                {{-- Medidor reativo: atualiza a cada "Salvar" --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <p class="text-xs uppercase tracking-wide text-gray-400">Compatibilidade com a vaga</p>
                    <div class="mt-3 flex items-center gap-4">
                        <svg width="88" height="88" viewBox="0 0 36 36" class="shrink-0 -rotate-90">
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e5e7eb" stroke-width="3.2" />
                            <circle cx="18" cy="18" r="15.9" fill="none" :stroke="scoreColor()" stroke-width="3.2" stroke-linecap="round" :stroke-dasharray="`${match.score} 100`" style="transition: stroke-dasharray .6s" />
                            <text x="18" y="21" text-anchor="middle" font-size="9" font-weight="700" :fill="scoreColor()" transform="rotate(90 18 18)" x-text="match.score + '%'"></text>
                        </svg>
                        <div class="text-sm">
                            <p class="font-semibold" :style="`color: ${scoreColor()}`" x-text="match.score >= 70 ? 'Ótimo match! 🎯' : (match.score >= 40 ? 'Dá para chegar lá 💪' : 'Precisa de ajustes 🔧')"></p>
                            <p class="text-gray-500">Salve para recalcular.</p>
                        </div>
                    </div>

                    <p class="mt-4 text-sm font-medium text-gray-800">✅ Você já tem</p>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <template x-for="t in match.matched" :key="t"><span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs border border-emerald-200" x-text="t"></span></template>
                        <span x-show="!match.matched.length" class="text-xs text-gray-400">nenhuma ainda</span>
                    </div>
                    <p class="mt-3 text-sm font-medium text-gray-800">❌ A vaga pede e não está no currículo</p>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <template x-for="t in match.missing" :key="t"><span class="px-2 py-0.5 rounded-full bg-red-50 text-red-700 text-xs border border-red-200" x-text="t"></span></template>
                        <span x-show="!match.missing.length" class="text-xs text-emerald-700">nada faltando 🎉</span>
                    </div>
                </div>

                <div x-show="placeholders.length" x-cloak class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-sm">
                    <p class="font-semibold text-amber-900">🖍 Complete no documento (<span x-text="placeholders.length"></span>)</p>
                    <ul class="mt-2 space-y-1.5 text-amber-900">
                        <template x-for="p in placeholders" :key="p"><li class="flex gap-2"><span>•</span><span x-text="p"></span></li></template>
                    </ul>
                    <p class="mt-2 text-xs text-amber-700">Só escreva se for verdade. Quando completar, apague o marca-texto (🖍).</p>
                </div>

                <a href="{{ $job->url }}" target="_blank" rel="noopener nofollow" class="block text-center px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-sm hover:bg-emerald-700">Ir para a vaga e se candidatar ↗</a>
            </x-resume-editor>
        </div>
    </div>
</x-app-layout>

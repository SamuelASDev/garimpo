<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <h1 class="text-2xl font-bold text-gray-900">Meu currículo</h1>
        <p class="text-gray-500 text-sm mt-1">Este é o seu currículo base. Para cada vaga, o Garimpo cria uma versão adaptada a partir dele.</p>

        @if (session('error'))
            <div class="mt-4 p-3 rounded-lg bg-amber-50 text-amber-800 text-sm">{{ session('error') }}</div>
        @endif

        <div class="mt-4">
            <x-resume-editor :config="[
                'html' => $html,
                'saveUrl' => route('resume.update'),
                'importUrl' => route('resume.import'),
                'wordUrl' => route('export.word'),
                'filename' => 'curriculo-'.auth()->user()->name,
                'placeholders' => \App\Services\ResumeTemplate::placeholders($html),
            ]">
                @if ($isExample)
                    <div class="bg-emerald-600 text-white rounded-xl p-5">
                        <p class="text-lg font-semibold">👋 Comece por aqui</p>
                        <p class="mt-2 text-sm text-emerald-50">Já tem currículo? Clique em <strong>📄 Importar PDF</strong>: a IA organiza tudo neste modelo.</p>
                        <p class="mt-2 text-sm text-emerald-50">Ou edite direto o modelo ao lado, substituindo o que está entre parênteses e colchetes.</p>
                    </div>
                @endif
                <div class="bg-white rounded-xl border border-gray-200 p-5 text-sm text-gray-600 space-y-2">
                    <p class="font-semibold text-gray-900">Dicas para passar no ATS</p>
                    <p>✅ Uma coluna, sem tabelas, fotos ou ícones.</p>
                    <p>✅ Títulos simples: Experiência, Formação, Habilidades.</p>
                    <p>✅ Escreva as tecnologias por extenso, do jeito que aparecem nas vagas.</p>
                    <p>✅ Atividades com verbo + resultado (ex.: "Reduzi em 30% o tempo...").</p>
                    <p>✅ Exporte em <strong>PDF</strong> (texto real) ou <strong>Word</strong>.</p>
                </div>
                <div x-show="placeholders.length" x-cloak class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-sm">
                    <p class="font-semibold text-amber-900">🖍 Trechos para completar</p>
                    <p class="text-amber-800 mt-1">Os itens em amarelo no documento precisam da sua informação.</p>
                </div>
            </x-resume-editor>
        </div>
    </div>
</x-app-layout>

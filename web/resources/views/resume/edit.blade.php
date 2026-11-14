@php
    $fields = [
        'full_name' => ['Nome completo', 'input', ''],
        'headline' => ['Título profissional', 'input', 'Ex.: Desenvolvedor PHP / Laravel'],
        'contact' => ['Contato', 'input', 'email · telefone · cidade · linkedin.com/in/... · github.com/...'],
        'summary' => ['Resumo profissional', 4, '3 a 5 linhas sobre você, o que faz e com quais tecnologias.'],
        'skills' => ['Competências', 3, 'PHP, Laravel, MySQL, Git, Docker, Inglês intermediário...'],
        'experience' => ['Experiência', 8, "Cargo — Empresa (mm/aaaa – mm/aaaa)\n- O que você fez e o resultado, citando as tecnologias"],
        'education' => ['Formação', 3, 'Curso — Instituição (ano de conclusão)'],
        'extras' => ['Cursos, certificações e idiomas', 3, ''],
    ];
@endphp
<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-2xl font-bold text-gray-900">Meu currículo</h1>
        <p class="text-gray-500 mt-1 text-sm">Preencha uma vez. Ele é comparado com cada vaga e serve de base para o currículo adaptado.</p>

        @if (session('status'))
            <div class="mt-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('resume.update') }}" class="mt-6 space-y-5 bg-white p-6 rounded-xl border border-gray-200">
            @csrf
            @method('PUT')
            @foreach ($fields as $name => [$label, $type, $placeholder])
                <div>
                    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">{{ $label }}</label>
                    @if ($type === 'input')
                        <input id="{{ $name }}" name="{{ $name }}" value="{{ old($name, $resume?->$name) }}" placeholder="{{ $placeholder }}"
                               @required($name === 'full_name') class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    @else
                        <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $type }}" placeholder="{{ $placeholder }}"
                                  class="mt-1 w-full rounded-lg border-gray-300 text-sm">{{ old($name, $resume?->$name) }}</textarea>
                    @endif
                    <x-input-error :messages="$errors->get($name)" class="mt-1" />
                </div>
            @endforeach
            <button class="px-5 py-2.5 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700">Salvar currículo</button>
        </form>
    </div>
</x-app-layout>

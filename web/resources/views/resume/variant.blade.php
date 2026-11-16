<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <a href="{{ route('resumes.index') }}" class="text-sm text-gray-500 hover:text-gray-800">&larr; Currículos</a>
        <div class="mt-1 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-bold text-gray-900">{{ $variant->name }}</h1>
            @if ($variant->category)
                <span class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 text-xs border border-indigo-200">{{ $variant->category }}</span>
            @endif
        </div>
        <p class="text-gray-500 text-sm mt-1">Variação do seu currículo. O que você muda aqui não altera o principal.</p>

        @if (session('status'))
            <div class="mt-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('status') }}</div>
        @endif

        <div class="mt-4">
            <x-resume-editor :config="[
                'html' => $variant->html,
                'saveUrl' => route('variants.update', $variant),
                'wordUrl' => route('export.word'),
                'pdfUrl' => route('export.pdf'),
                'filename' => 'curriculo-'.$variant->name,
                'placeholders' => \App\Services\ResumeTemplate::placeholders($variant->html),
                'recommendations' => $variant->recommendations ?? [],
                'assistantUrl' => route('variants.assistant', $variant),
                'suggestUrl' => route('variants.suggestions', $variant),
            ]">
                <div class="bg-white rounded-xl border border-gray-200 p-5 text-sm" x-data="{ editando: {{ $errors->any() ? 'true' : 'false' }} }">
                    <div class="flex items-center justify-between">
                        <p class="text-xs uppercase tracking-wide text-gray-400">Sobre esta variação</p>
                        <button type="button" @click="editando = !editando" class="text-xs text-emerald-700 hover:underline" x-text="editando ? 'Fechar' : 'Editar'"></button>
                    </div>
                    <p x-show="!editando" class="mt-2 text-gray-600">{{ $variant->description ?: 'Sem descrição. Descreva o foco para a IA sugerir melhor.' }}</p>
                    <form x-show="editando" x-cloak method="POST" action="{{ route('variants.details', $variant) }}" class="mt-3 space-y-3">
                        @csrf @method('PATCH')
                        <label class="block">
                            <span class="text-gray-700">Nome</span>
                            <input name="name" required maxlength="80" value="{{ old('name', $variant->name) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            <x-input-error :messages="$errors->get('name')" />
                        </label>
                        <label class="block">
                            <span class="text-gray-700">Categoria</span>
                            <input name="category" list="variant-categories" maxlength="40" value="{{ old('category', $variant->category) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            <datalist id="variant-categories">
                                @foreach ($categories as $category)<option value="{{ $category }}">@endforeach
                            </datalist>
                        </label>
                        <label class="block">
                            <span class="text-gray-700">Descrição</span>
                            <textarea name="description" rows="3" maxlength="300" class="mt-1 w-full rounded-lg border-gray-300 text-sm">{{ old('description', $variant->description) }}</textarea>
                        </label>
                        <button class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Salvar dados</button>
                    </form>
                    <p class="mt-3 text-xs text-gray-400">A IA usa o nome, a categoria e a descrição como alvo: ajusta o cargo alvo e o objetivo e sugere o que destacar ou tirar.</p>
                </div>
                <div x-show="placeholders.length" x-cloak class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-sm">
                    <p class="font-semibold text-amber-900">🖍 Trechos para completar</p>
                    <p class="text-amber-800 mt-1">Os itens em amarelo no documento precisam da sua informação.</p>
                </div>
            </x-resume-editor>
        </div>
    </div>
</x-app-layout>

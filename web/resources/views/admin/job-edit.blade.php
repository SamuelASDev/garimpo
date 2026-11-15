<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @include('admin._nav')

        <form method="POST" action="{{ route('admin.jobs.update', $job) }}" class="mt-6 bg-white rounded-xl border border-gray-200 p-5 space-y-4 text-sm">
            @csrf @method('PUT')
            <div class="grid sm:grid-cols-2 gap-4">
                @foreach (['title' => 'Cargo', 'company' => 'Empresa', 'location' => 'Local', 'url' => 'Link do anúncio'] as $field => $label)
                    <label class="block">
                        <span class="text-gray-600">{{ $label }}</span>
                        <input name="{{ $field }}" value="{{ old($field, $job->$field) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </label>
                @endforeach
                <label class="block">
                    <span class="text-gray-600">Senioridade</span>
                    <select name="seniority" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <option value="">Não identificada</option>
                        @foreach (\App\Services\JobClassifier::SENIORITIES as $s)
                            <option value="{{ $s }}" @selected(old('seniority', $job->seniority) === $s)>{{ $labels[$s] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-gray-600">Área</span>
                    <select name="area" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <option value="">Não identificada</option>
                        @foreach (array_keys(\App\Services\JobClassifier::AREAS) as $a)
                            <option value="{{ $a }}" @selected(old('area', $job->area) === $a)>{{ $labels[$a] ?? $a }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <label class="block">
                <span class="text-gray-600">Tecnologias (separadas por vírgula; só as que o filtro conhece são mantidas)</span>
                <input name="techs" value="{{ old('techs', implode(', ', $job->techs ?? [])) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="remote" value="1" @checked(old('remote', $job->remote)) class="rounded border-gray-300 text-emerald-600">
                <span>Remoto</span>
            </label>
            <label class="block">
                <span class="text-gray-600">Descrição</span>
                <textarea name="description" rows="18" class="mt-1 w-full rounded-lg border-gray-300 text-sm font-mono">{{ old('description', $job->description) }}</textarea>
            </label>
            <div class="flex gap-3">
                <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Salvar</button>
                <a href="{{ route('admin.jobs') }}" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-700">Cancelar</a>
            </div>
        </form>
    </div>
</x-app-layout>

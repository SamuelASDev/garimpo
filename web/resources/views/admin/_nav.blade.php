<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold text-gray-900">🛠️ Administração</h1>
    <div class="flex gap-2 text-sm">
        @foreach (['admin.index' => 'Resumo', 'admin.jobs' => 'Vagas', 'admin.users' => 'Usuários', 'stats' => 'Estatísticas'] as $route => $label)
            <a href="{{ route($route) }}" @class(['px-3 py-1.5 rounded-lg', 'bg-emerald-600 text-white' => request()->routeIs($route), 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' => ! request()->routeIs($route)])>{{ $label }}</a>
        @endforeach
    </div>
</div>

@if (session('status'))
    <div class="mt-4 p-3 rounded-lg text-sm bg-emerald-50 text-emerald-700">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="mt-4 p-3 rounded-lg text-sm bg-red-50 text-red-700">{{ $errors->first() }}</div>
@endif

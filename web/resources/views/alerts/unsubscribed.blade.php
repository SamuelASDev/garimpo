<x-guest-layout>
    <h2 class="text-2xl font-bold text-gray-900">Pronto, alertas pausados</h2>
    <p class="mt-3 text-sm text-gray-600">Você não vai mais receber e-mails de alertas de vagas do {{ config('app.name') }}.
        Se mudar de ideia, é só entrar e reativar em <strong>Alertas</strong>.</p>
    <a href="{{ route('login') }}" class="mt-6 inline-block px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700">Entrar</a>
</x-guest-layout>

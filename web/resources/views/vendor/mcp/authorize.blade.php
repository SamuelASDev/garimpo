<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Autorizar acesso · {{ config('app.name', 'Garimpo') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
{{-- Tela "Permitir que <IA> acesse sua conta do Garimpo?" (login OAuth das IAs conectadas), no visual do site. --}}
<body class="font-sans antialiased bg-gray-50 text-gray-900">
<div class="min-h-screen flex flex-col items-center justify-center p-4">
    <a href="{{ route('jobs.index') }}" class="flex items-center gap-2 font-bold text-gray-900 mb-6">
        <x-application-logo class="w-9 h-9" />
        {{ config('app.name') }}
    </a>

    <div class="w-full max-w-md bg-white rounded-2xl border border-gray-200 shadow-sm">
        <div class="p-6 text-center">
            <div class="mx-auto w-14 h-14 rounded-2xl bg-emerald-50 flex items-center justify-center text-3xl" aria-hidden="true">🤖</div>
            <h1 class="mt-4 text-xl font-bold">Conectar {{ $client->name }} ao {{ config('app.name') }}?</h1>
            <p class="mt-2 text-sm text-gray-600">
                Este aplicativo poderá, em seu nome: <strong>buscar vagas</strong>, <strong>ler e editar seus currículos</strong>
                e <strong>acompanhar suas candidaturas</strong>.
            </p>
        </div>

        <div class="px-6 space-y-3 text-sm">
            <div class="rounded-xl bg-gray-50 border border-gray-200 p-3">
                <p class="text-xs text-gray-500">Conta do {{ config('app.name') }}</p>
                <p class="font-medium text-gray-900 break-all">{{ $user->email }}</p>
            </div>

            @php($redirectUri = request()->query('redirect_uri', $client->redirect_uris[0] ?? null))
            @if ($redirectUri)
                <p class="text-xs text-gray-500 break-all">Depois de autorizar, você volta para <span class="font-mono text-gray-700">{{ $redirectUri }}</span></p>
            @endif

            <ul class="text-gray-600 space-y-1.5">
                <li class="flex gap-2"><span class="text-emerald-600">✓</span> Nada é alterado sem você pedir para a IA.</li>
                <li class="flex gap-2"><span class="text-emerald-600">✓</span> Você pode desconectar quando quiser em <strong>Seção dev</strong>.</li>
            </ul>
        </div>

        <div class="p-6 flex gap-3">
            <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="">
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="w-full h-11 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">Cancelar</button>
            </form>

            <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1" id="authorizeForm">
                @csrf
                <input type="hidden" name="state" value="">
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" id="authorizeButton" class="w-full h-11 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 disabled:opacity-60 inline-flex items-center justify-center gap-2">
                    <svg id="loadingSpinner" class="animate-spin h-4 w-4 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span id="authorizeText">Autorizar</span>
                </button>
            </form>
        </div>
    </div>

    <p class="mt-6 text-xs text-gray-400">Só autorize se foi você que pediu para conectar esta IA agora.</p>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('authorizeForm');
        const button = document.getElementById('authorizeButton');
        const authorizeText = document.getElementById('authorizeText');
        const loadingSpinner = document.getElementById('loadingSpinner');

        form.addEventListener('submit', function(e) {
            // Show loading state...
            button.disabled = true;
            authorizeText.textContent = 'Autorizando...';
            loadingSpinner.classList.remove('hidden');

            // After form submission, watch for redirect and close window...
            setTimeout(function() {
                const checkRedirect = setInterval(function() {
                    // If URL changed or we have OAuth params, redirect happened...
                    if (!window.location.href.includes('/oauth/authorize') ||
                        window.location.search.includes('code=') ||
                        window.location.search.includes('error=')) {
                        clearInterval(checkRedirect);
                        window.close();
                    }
                }, 100);

                // Fallback: Close after five seconds...
                setTimeout(function() {
                    clearInterval(checkRedirect);
                    window.close();
                }, 5000);
            }, 200);
        });

        // Handle cancel button...
        const cancelForm = document.querySelector('form[method="POST"]:has(input[name="_method"][value="DELETE"])');
        if (cancelForm) {
            cancelForm.addEventListener('submit', function(e) {
                setTimeout(function() {
                    window.close();
                }, 200);
            });
        }
    });
</script>
</body>
</html>

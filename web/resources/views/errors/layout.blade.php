<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ config('app.name', 'Garimpo') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    {{-- CSS embutido de propósito: a tela de erro precisa abrir mesmo se o build/Vite ou o banco estiverem com problema. --}}
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f9fafb; color: #111827; }
        .card { max-width: 480px; width: 100%; background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 32px; text-align: center; }
        .emoji { font-size: 48px; line-height: 1; }
        .code { margin-top: 12px; font-size: 13px; font-weight: 600; letter-spacing: .08em; color: #059669; }
        h1 { margin: 6px 0 0; font-size: 22px; }
        p { margin: 12px 0 0; color: #4b5563; line-height: 1.5; }
        .actions { margin-top: 24px; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        a, button { font: inherit; font-size: 14px; padding: 10px 16px; border-radius: 10px; text-decoration: none; cursor: pointer; }
        .primary { background: #059669; color: #fff; border: 0; }
        .primary:hover { background: #047857; }
        .secondary { background: #fff; color: #374151; border: 1px solid #d1d5db; }
        .brand { margin-top: 24px; font-size: 13px; color: #9ca3af; }
    </style>
</head>
<body>
    <main class="card">
        <div class="emoji" aria-hidden="true">@yield('emoji')</div>
        <div class="code">ERRO @yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            @section('actions')
                <a class="primary" href="/">Ver as vagas</a>
                <button class="secondary" type="button" onclick="history.back()">Voltar</button>
            @show
        </div>
        <div class="brand">💎 {{ config('app.name', 'Garimpo') }}</div>
    </main>
</body>
</html>

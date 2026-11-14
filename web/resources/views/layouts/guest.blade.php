<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gray-50">
        <div class="min-h-screen grid lg:grid-cols-2">
            {{-- Painel da marca --}}
            <aside class="relative overflow-hidden bg-gradient-to-br from-emerald-700 via-emerald-800 to-emerald-950 text-white px-6 py-6 lg:p-14 flex flex-col">
                <div class="absolute -right-24 -top-24 w-80 h-80 rounded-full bg-amber-400/10 blur-2xl" aria-hidden="true"></div>
                <div class="absolute -left-16 bottom-0 w-72 h-72 rounded-full bg-emerald-400/10 blur-2xl" aria-hidden="true"></div>

                <a href="{{ route('jobs.index') }}" class="relative flex items-center gap-3 w-fit">
                    <x-application-logo class="w-10 h-10 ring-1 ring-white/20 rounded-[14px]" />
                    <span class="text-xl font-bold tracking-tight">{{ config('app.name') }}</span>
                </a>

                <div class="relative mt-5 lg:mt-auto max-w-md">
                    <h1 class="text-xl sm:text-2xl lg:text-4xl font-bold leading-tight">Garimpe as vagas certas.<br><span class="text-amber-300">Chegue no ATS pronto.</span></h1>
                    <ul class="hidden lg:block mt-10 space-y-4 text-emerald-50">
                        <li class="flex gap-3"><span class="text-amber-300">◆</span><span>Vagas de TI de vários sites num lugar só, sem repetição.</span></li>
                        <li class="flex gap-3"><span class="text-amber-300">◆</span><span>Veja quanto seu currículo combina com cada vaga e o que falta.</span></li>
                        <li class="flex gap-3"><span class="text-amber-300">◆</span><span>Currículo adaptado por vaga com IA, sem inventar nada.</span></li>
                        <li class="flex gap-3"><span class="text-amber-300">◆</span><span>Grátis e open source.</span></li>
                    </ul>
                </div>

                <p class="relative hidden lg:block mt-auto pt-10 text-xs text-emerald-200/70">Seus dados são seus: exporte ou apague quando quiser (LGPD).</p>
            </aside>

            {{-- Formulário --}}
            <main class="flex flex-col justify-center px-6 py-10 lg:px-16">
                <div class="w-full max-w-md mx-auto">
                    {{ $slot }}
                </div>
                <p class="mt-10 text-xs text-gray-400 text-center">
                    <a href="{{ route('legal.privacy') }}" class="hover:text-gray-600">Política de Privacidade</a> ·
                    <a href="{{ route('legal.terms') }}" class="hover:text-gray-600">Termos de Uso</a>
                </p>
            </main>
        </div>
    </body>
</html>

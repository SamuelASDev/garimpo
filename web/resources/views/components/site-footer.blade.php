@props(['compact' => false])
{{-- Rodapé do Garimpo: crédito do autor, links e páginas legais --}}
@php
    $links = [
        ['Portfólio', 'https://portifolio.froglabs.site/samuel-abreu-sales', 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm6.93 6h-2.95a15.6 15.6 0 0 0-1.38-3.56A8.03 8.03 0 0 1 18.93 8ZM12 4.04c.83 1.2 1.48 2.53 1.91 3.96h-3.82c.43-1.43 1.08-2.76 1.91-3.96ZM4.26 14a8.2 8.2 0 0 1 0-4h3.38a16.5 16.5 0 0 0 0 4H4.26Zm.81 2h2.95c.32 1.25.78 2.45 1.38 3.56A8 8 0 0 1 5.07 16Zm2.95-8H5.07a8 8 0 0 1 4.33-3.56A15.6 15.6 0 0 0 8.02 8ZM12 19.96A14.1 14.1 0 0 1 10.09 16h3.82A14.1 14.1 0 0 1 12 19.96ZM14.34 14H9.66a14.7 14.7 0 0 1 0-4h4.68a14.7 14.7 0 0 1 0 4Zm.26 5.56c.6-1.11 1.06-2.31 1.38-3.56h2.95a8.03 8.03 0 0 1-4.33 3.56ZM16.36 14a16.5 16.5 0 0 0 0-4h3.38a8.2 8.2 0 0 1 0 4h-3.38Z'],
        ['LinkedIn', 'https://www.linkedin.com/in/samuel-abreu-sales/', 'M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05a3.74 3.74 0 0 1 3.37-1.85c3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13ZM7.12 20.45H3.56V9h3.56v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0Z'],
        ['GitHub', 'https://github.com/SamuelASDev', 'M12 .3a12 12 0 0 0-3.8 23.38c.6.12.83-.26.83-.57L9 21.07c-3.34.72-4.04-1.61-4.04-1.61-.55-1.39-1.34-1.76-1.34-1.76-1.08-.74.09-.73.09-.73 1.2.09 1.83 1.24 1.83 1.24 1.08 1.83 2.81 1.3 3.5 1 .1-.78.42-1.31.76-1.61-2.67-.3-5.47-1.33-5.47-5.93 0-1.31.47-2.38 1.24-3.22-.14-.3-.54-1.52.1-3.18 0 0 1-.32 3.3 1.23a11.5 11.5 0 0 1 6 0c2.28-1.55 3.29-1.23 3.29-1.23.64 1.66.24 2.88.12 3.18a4.65 4.65 0 0 1 1.23 3.22c0 4.61-2.8 5.63-5.48 5.92.42.36.81 1.1.81 2.22l-.01 3.29c0 .31.2.69.82.57A12 12 0 0 0 12 .3Z'],
    ];
@endphp
<footer {{ $attributes->merge(['class' => 'print:hidden '.($compact ? 'mt-10' : 'mt-16 border-t border-gray-200 bg-white')]) }}>
    <div @class(['mx-auto px-4 sm:px-6 lg:px-8 flex flex-col items-center gap-3 text-center', 'max-w-6xl py-8 sm:flex-row sm:justify-between sm:text-left' => ! $compact])>
        <div class="text-sm text-gray-500">
            <p>Desenvolvido por <a href="https://portifolio.froglabs.site/samuel-abreu-sales" target="_blank" rel="noopener" class="font-medium text-gray-800 hover:text-emerald-700">Samuel Abreu Sales</a></p>
            <p class="mt-1 text-xs text-gray-400">
                {{ config('app.name') }} · open source (MIT) ·
                <a href="{{ route('legal.privacy') }}" class="hover:text-gray-600">Privacidade</a> ·
                <a href="{{ route('legal.terms') }}" class="hover:text-gray-600">Termos</a> ·
                <a href="{{ route('api.docs') }}" class="hover:text-gray-600">API</a>
            </p>
        </div>
        <div class="flex items-center gap-2">
            @foreach ($links as [$label, $url, $path])
                <a href="{{ $url }}" target="_blank" rel="noopener" title="{{ $label }}" aria-label="{{ $label }} de Samuel Abreu Sales"
                   class="grid place-items-center w-9 h-9 rounded-full text-gray-500 hover:text-white hover:bg-emerald-600 transition">
                    <svg viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="currentColor" aria-hidden="true"><path d="{{ $path }}"/></svg>
                </a>
            @endforeach
        </div>
    </div>
</footer>

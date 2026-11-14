<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Privacidade e seus dados</h2>
        <p class="mt-1 text-sm text-gray-600">Você controla o que é feito com seus dados (LGPD). Veja a <a href="{{ route('legal.privacy') }}" class="underline">Política de Privacidade</a>.</p>
    </header>

    <form method="POST" action="{{ route('privacy.consent') }}" class="mt-6 space-y-3">
        @csrf @method('PATCH')
        <input type="hidden" name="ai_consent" value="{{ $user->hasAiConsent() ? 0 : 1 }}">
        <p class="text-sm text-gray-700">
            <strong>IA grátis:</strong>
            @if ($user->hasAiConsent())
                <span class="text-emerald-700">ativada desde {{ $user->ai_consent_at->format('d/m/Y') }}</span>.
                O texto do currículo, sem telefone, e-mail e links, é enviado a provedores de IA fora do Brasil (Groq, OpenRouter, Google) quando você usa as funções de IA.
            @else
                <span class="text-gray-500">desligada</span>. Nada é enviado a provedores de IA. Funções de IA ficam indisponíveis (sua própria IA conectada continua funcionando).
            @endif
        </p>
        <button class="px-4 py-2 rounded-lg text-sm {{ $user->hasAiConsent() ? 'border border-gray-300 hover:bg-gray-50' : 'bg-emerald-600 text-white hover:bg-emerald-700' }}">
            {{ $user->hasAiConsent() ? 'Desligar IA grátis' : 'Autorizar e ativar a IA grátis' }}
        </button>
    </form>

    <div class="mt-6 pt-6 border-t border-gray-100">
        <p class="text-sm text-gray-700"><strong>Seus dados:</strong> baixe uma cópia de tudo o que o Garimpo guarda sobre você (conta, currículos, candidaturas e conexões de IA).</p>
        <a href="{{ route('privacy.export') }}" class="mt-3 inline-block px-4 py-2 rounded-lg border border-gray-300 text-sm hover:bg-gray-50">⬇ Baixar meus dados (JSON)</a>
    </div>
</section>

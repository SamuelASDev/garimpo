@php
    $base = url('/api/v1');
    $code = 'block overflow-x-auto rounded-lg bg-gray-900 text-gray-100 text-xs p-4 leading-relaxed';
    $params = [
        ['q', 'texto', 'Busca no título e na empresa.'],
        ['tecnologia', 'PHP,Laravel', 'Uma ou mais tecnologias separadas por vírgula (a vaga precisa ter todas).'],
        ['senioridade', implode(' | ', \App\Services\JobClassifier::SENIORITIES), 'Nível da vaga.'],
        ['area', implode(' | ', array_keys(\App\Services\JobClassifier::AREAS)), 'Área de TI.'],
        ['modo', implode(' | ', array_keys(\App\Services\JobClassifier::WORK_MODES)), 'Modo de trabalho.'],
        ['cidade', 'São Paulo', 'Cidade (sem diferença de acento ou maiúsculas).'],
        ['uf', 'SP', 'Estado (sigla).'],
        ['desde', '2026-11-18T12:00:00Z', 'Só vagas que entraram no Garimpo a partir desta data/hora (as datas da API vêm em UTC). Ideal para sincronizar.'],
        ['por_pagina', '1 a 50', 'Vagas por página (padrão 20).'],
        ['page', '1, 2, 3…', 'Página.'],
    ];
@endphp
<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <p class="text-xs uppercase tracking-widest text-emerald-700 font-semibold">Para desenvolvedores</p>
        <h1 class="mt-1 text-3xl font-bold text-gray-900">API do Garimpo</h1>
        <p class="mt-2 text-gray-600">As vagas de TI que o Garimpo coleta, já sem duplicadas e classificadas (senioridade, área, modo, cidade, tecnologias e stack), em JSON. Somente leitura, grátis e com limite justo.</p>

        <div class="mt-6 grid sm:grid-cols-3 gap-3 text-sm">
            <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-2xl font-bold text-gray-900">60<span class="text-sm font-normal text-gray-500">/min</span></p><p class="text-gray-500">requisições por chave</p></div>
            <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-2xl font-bold text-gray-900">5.000<span class="text-sm font-normal text-gray-500">/dia</span></p><p class="text-gray-500">requisições por chave</p></div>
            <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-2xl font-bold text-gray-900">a cada 4h</p><p class="text-gray-500">vagas novas (respostas em cache por 5 min)</p></div>
        </div>

        <h2 class="mt-10 text-xl font-semibold text-gray-900">1. Crie sua chave</h2>
        <p class="mt-2 text-sm text-gray-600">
            @auth Vá em <a href="{{ route('profile.edit') }}#chaves-api" class="text-emerald-700 underline">Minha conta → Chaves da API</a> e clique em <strong>Criar chave</strong>.
            @else <a href="{{ route('register') }}" class="text-emerald-700 underline">Crie uma conta grátis</a> e depois vá em Minha conta → Chaves da API.
            @endauth
            A chave aparece uma vez só: guarde em lugar seguro e use apenas no servidor (nunca no JavaScript do navegador).
        </p>

        <h2 class="mt-10 text-xl font-semibold text-gray-900">2. Faça a primeira chamada</h2>
        <p class="mt-2 text-sm text-gray-600">Envie a chave no cabeçalho <code class="text-emerald-700">Authorization: Bearer</code> (ou <code class="text-emerald-700">X-Api-Key</code>).</p>
        <pre class="mt-3"><code class="{{ $code }}">curl "{{ $base }}/vagas?tecnologia=PHP&amp;modo=remoto&amp;por_pagina=5" \
  -H "Authorization: Bearer SUA_CHAVE"</code></pre>

        <h2 class="mt-10 text-xl font-semibold text-gray-900">Endpoints</h2>

        <div class="mt-4 bg-white rounded-xl border border-gray-200 p-5">
            <p class="font-mono text-sm"><span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold">GET</span> /api/v1/vagas</p>
            <p class="mt-2 text-sm text-gray-600">Lista as vagas, das mais recentes para as mais antigas. Todos os filtros são opcionais e podem ser combinados.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-gray-500 border-b"><th class="py-2 pr-4">Parâmetro</th><th class="py-2 pr-4">Exemplo</th><th class="py-2">O que faz</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($params as [$name, $example, $help])
                            <tr><td class="py-2 pr-4 font-mono text-emerald-700">{{ $name }}</td><td class="py-2 pr-4 font-mono text-xs text-gray-700">{{ $example }}</td><td class="py-2 text-gray-600">{{ $help }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-4 text-sm font-medium text-gray-800">Resposta</p>
            <pre class="mt-2"><code class="{{ $code }}">{
  "data": [
    {
      "id": 1234,
      "titulo": "Desenvolvedor PHP Pleno",
      "empresa": "Empresa X",
      "local": "São Paulo, SP",
      "cidade": "São Paulo",
      "uf": "SP",
      "modo": "hibrido",
      "senioridade": "pleno",
      "area": "dev",
      "stack": "PHP",
      "tecnologias": ["PHP", "Laravel", "MySQL"],
      "competencias": ["APIs REST", "Git"],
      "resumo": "Primeiras linhas da vaga (até 280 caracteres)...",
      "fonte": "gupy",
      "url_original": "https://...",
      "url_garimpo": "{{ url('/vagas/1234') }}",
      "publicada_em": "2026-11-18T11:00:00+00:00",
      "coletada_em": "2026-11-18T12:02:11+00:00"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "last_page": 12, "per_page": 20, "total": 231 }
}</code></pre>
        </div>

        <div class="mt-4 bg-white rounded-xl border border-gray-200 p-5">
            <p class="font-mono text-sm"><span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold">GET</span> /api/v1/vagas/{id}</p>
            <p class="mt-2 text-sm text-gray-600">Uma vaga, no mesmo formato acima (dentro de <code>data</code>). Vaga que não existe mais: 404.</p>
        </div>

        <div class="mt-4 bg-white rounded-xl border border-gray-200 p-5">
            <p class="font-mono text-sm"><span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold">GET</span> /api/v1/estatisticas?dias=30</p>
            <p class="mt-2 text-sm text-gray-600">Contagens do período (<code>dias</code> = 7, 30 ou 90): total de vagas, tecnologias, stacks, senioridades, áreas, modos e fontes. São os números da página de <a href="{{ route('stats') }}" class="text-emerald-700 underline">estatísticas</a>.</p>
        </div>

        <h2 class="mt-10 text-xl font-semibold text-gray-900">Erros e limites</h2>
        <ul class="mt-3 space-y-2 text-sm text-gray-600">
            <li><code class="font-mono text-gray-900">401</code> chave ausente, errada ou revogada.</li>
            <li><code class="font-mono text-gray-900">422</code> filtro inválido (a resposta diz qual).</li>
            <li><code class="font-mono text-gray-900">429</code> passou do limite: espere os segundos do cabeçalho <code>Retry-After</code>. Os cabeçalhos <code>X-RateLimit-Remaining</code> mostram quanto ainda resta.</li>
        </ul>

        <h2 class="mt-10 text-xl font-semibold text-gray-900">Regras de uso</h2>
        <ul class="mt-3 space-y-2 text-sm text-gray-600 list-disc ps-5">
            <li>Sempre mostre o link <code>url_original</code>: a vaga pertence ao site de origem, é lá que a pessoa se candidata.</li>
            <li>Cite a fonte: "vagas via <a href="{{ route('home') }}" class="text-emerald-700 underline">Garimpo</a>".</li>
            <li>A descrição completa não vem pela API (é conteúdo do site de origem); o <code>resumo</code> traz só o começo.</li>
            <li>Não tente burlar os limites com várias contas. Chaves usadas para abuso são revogadas.</li>
            <li>Para sincronizar, use <code>desde</code> com a data da sua última chamada em vez de baixar tudo de novo.</li>
        </ul>
    </div>
</x-app-layout>

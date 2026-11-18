@php($labels = \App\Services\JobClassifier::LABELS)
<x-app-layout>
    {{-- Apresentação --}}
    <section class="bg-gradient-to-br from-emerald-700 via-emerald-800 to-emerald-950 text-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-14 sm:py-20 grid lg:grid-cols-[1.3fr_1fr] gap-10 items-center">
            <div>
                <p class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-emerald-100 text-sm">💎 Grátis e de código aberto</p>
                <h1 class="mt-4 text-3xl sm:text-5xl font-bold leading-tight">Garimpe as vagas de TI certas.<br><span class="text-amber-300">Chegue no ATS pronto.</span></h1>
                <p class="mt-5 text-lg text-emerald-50 max-w-xl">
                    O {{ config('app.name') }} junta vagas de tecnologia de vários sites num lugar só, sem repetidas, e te ajuda a
                    adaptar o currículo para cada uma, mostrando o que falta para passar nos filtros automáticos das empresas.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('jobs.index') }}" class="px-5 py-3 rounded-xl bg-amber-400 text-gray-900 font-semibold hover:bg-amber-300">Ver as vagas agora</a>
                    @guest
                        <a href="{{ route('register') }}" class="px-5 py-3 rounded-xl bg-white/10 ring-1 ring-white/30 font-semibold hover:bg-white/20">Criar conta grátis</a>
                    @else
                        <a href="{{ route('resumes.index') }}" class="px-5 py-3 rounded-xl bg-white/10 ring-1 ring-white/30 font-semibold hover:bg-white/20">Meus currículos</a>
                    @endguest
                </div>
                <p class="mt-3 text-sm text-emerald-200">Não precisa de conta para ver as vagas e as estatísticas.</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                @foreach ([
                    [$jobs, 'vagas de TI agora'],
                    [$sources, 'sites de vagas reunidos'],
                    ['4 em 4h', 'coleta automática'],
                    [$updated ? \Illuminate\Support\Carbon::parse($updated)->diffForHumans() : '—', 'última vaga nova'],
                ] as [$value, $label])
                    <div class="rounded-2xl bg-white/10 ring-1 ring-white/15 p-5">
                        <p class="text-2xl sm:text-3xl font-bold">{{ $value }}</p>
                        <p class="text-sm text-emerald-100">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-14 space-y-16">
        {{-- Como funciona --}}
        <section>
            <h2 class="text-2xl font-bold text-gray-900">Como funciona</h2>
            <p class="text-gray-500 mt-1">Do anúncio à candidatura, em quatro passos.</p>
            <ol class="mt-6 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ([
                    ['🔎', 'Encontre a vaga', 'Filtre por senioridade, área, tecnologia, cidade e modalidade (remoto, híbrido, presencial). Vagas repetidas em vários sites aparecem uma vez só.'],
                    ['📄', 'Monte seu currículo', 'Importe seu PDF ou escreva no editor, no modelo que os sistemas de recrutamento (ATS) leem bem. Baixe em PDF ou Word.'],
                    ['🎯', 'Adapte para cada vaga', 'Veja o % de compatibilidade e o que falta. A IA reescreve o currículo para a vaga e sugere o que tirar, ajustar e adicionar, sem inventar nada.'],
                    ['📬', 'Acompanhe e receba alertas', 'Registre suas candidaturas e etapas. Crie alertas e receba no máximo um e-mail por dia com as vagas novas do seu jeito.'],
                ] as $i => [$icon, $title, $text])
                    <li class="bg-white rounded-2xl border border-gray-200 p-5">
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-xl" aria-hidden="true">{{ $icon }}</span>
                            <span class="text-xs font-semibold text-emerald-700">PASSO {{ $i + 1 }}</span>
                        </div>
                        <h3 class="mt-3 font-semibold text-gray-900">{{ $title }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Sem conta x com conta --}}
        <section class="grid md:grid-cols-2 gap-4">
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="text-lg font-bold text-gray-900">🔓 Sem conta, você já pode</h2>
                <ul class="mt-4 space-y-2 text-sm text-gray-700">
                    <li>✓ Ver todas as vagas, com a descrição completa e o link do anúncio original</li>
                    <li>✓ Filtrar por senioridade, área, tecnologias, cidade/estado e modalidade</li>
                    <li>✓ Ver as estatísticas do mercado: stacks, tecnologias e competências mais pedidas</li>
                </ul>
                <div class="mt-5 flex flex-wrap gap-3 text-sm">
                    <a href="{{ route('jobs.index') }}" class="px-4 py-2 rounded-lg bg-gray-900 text-white hover:bg-gray-700">Ver vagas</a>
                    <a href="{{ route('stats') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Ver estatísticas</a>
                </div>
            </div>
            <div class="bg-white rounded-2xl border border-emerald-200 p-6">
                <h2 class="text-lg font-bold text-gray-900">✨ Com uma conta grátis, você ganha</h2>
                <ul class="mt-4 space-y-2 text-sm text-gray-700">
                    <li>✓ Editor de currículo com modelo ATS, importação de PDF e exportação em PDF/Word</li>
                    <li>✓ Currículo adaptado para cada vaga, com o que falta e sugestões da IA</li>
                    <li>✓ Controle das suas candidaturas e alertas de vagas por e-mail</li>
                    <li>✓ Conectar a sua própria IA (Claude, ChatGPT...) ao {{ config('app.name') }}</li>
                </ul>
                @guest
                    <div class="mt-5 text-sm"><a href="{{ route('register') }}" class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Criar conta grátis</a></div>
                @endguest
            </div>
        </section>

        {{-- O que é ATS --}}
        <section class="grid lg:grid-cols-[1fr_1.2fr] gap-8 items-center">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">O que é ATS e por que isso importa?</h2>
                <p class="mt-3 text-gray-600">
                    ATS (<em>Applicant Tracking System</em>) é o sistema que as empresas usam para receber currículos. Antes de uma
                    pessoa ler o seu, ele procura as <strong>palavras exatas</strong> que a vaga pede e dá uma nota. Currículo sem
                    esses termos, ou com layout que o sistema não lê, costuma ser descartado sem ninguém ver.
                </p>
                <p class="mt-3 text-gray-600">
                    O {{ config('app.name') }} compara o seu currículo com cada vaga, mostra o que está faltando e ajuda a escrever do
                    jeito certo: verbo de ação, resultado, os termos da vaga e uma página só.
                </p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <p class="text-sm font-semibold text-gray-900">Exemplo: currículo x vaga de Desenvolvedor PHP Júnior</p>
                <div class="mt-4 flex items-center gap-4">
                    <x-score-ring :score="72" />
                    <div class="text-sm text-gray-600">
                        <p><span class="text-emerald-700 font-medium">Você já tem:</span> PHP, Laravel, MySQL, Git</p>
                        <p class="mt-1"><span class="text-amber-700 font-medium">Falta citar:</span> Docker, testes automatizados</p>
                    </div>
                </div>
                <p class="mt-4 text-xs text-gray-500">Se você já usou o que falta, a IA indica onde e como colocar. Se não usou, ela não inventa.</p>
            </div>
        </section>

        {{-- Mercado agora --}}
        <section class="grid lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-bold text-gray-900">📊 Stacks com mais vagas (30 dias)</h2>
                    <a href="{{ route('stats') }}" class="text-sm text-emerald-700 hover:underline">Ver todas as estatísticas</a>
                </div>
                @php($max = max(1, ...array_values($stacks ?: [0])))
                <div class="mt-4 space-y-2">
                    @forelse ($stacks as $stack => $total)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-44 shrink-0 truncate text-gray-700">{{ $stack }}</span>
                            <div class="flex-1 h-3 bg-gray-100 rounded"><div class="h-3 rounded bg-indigo-500" style="width: {{ $total / $max * 100 }}%"></div></div>
                            <span class="w-8 text-right text-gray-500 tabular-nums">{{ $total }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">As estatísticas aparecem depois da primeira coleta.</p>
                    @endforelse
                </div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-bold text-gray-900">🆕 Vagas mais recentes</h2>
                    <a href="{{ route('jobs.index') }}" class="text-sm text-emerald-700 hover:underline">Ver todas</a>
                </div>
                <ul class="mt-3 divide-y divide-gray-100">
                    @forelse ($latest as $job)
                        <li class="py-2.5">
                            <a href="{{ route('jobs.show', $job) }}" class="font-medium text-gray-900 hover:underline">{{ $job->title }}</a>
                            <p class="text-xs text-gray-500">
                                {{ $job->company ?? 'Empresa não informada' }}
                                · {{ $job->city ? $job->city.($job->state ? ' — '.$job->state : '') : (\App\Services\JobClassifier::WORK_MODES[$job->work_mode] ?? 'Local não informado') }}
                                @if ($job->seniority) · {{ $labels[$job->seniority] }}@endif
                            </p>
                        </li>
                    @empty
                        <li class="py-2.5 text-sm text-gray-500">Nenhuma vaga ainda.</li>
                    @endforelse
                </ul>
            </div>
        </section>

        {{-- IA conectada --}}
        <section class="bg-white rounded-2xl border border-gray-200 p-6 sm:p-8 grid md:grid-cols-[auto_1fr] gap-6 items-center">
            <div class="text-5xl" aria-hidden="true">🤖</div>
            <div>
                <h2 class="text-xl font-bold text-gray-900">Use a sua IA dentro do {{ config('app.name') }}</h2>
                <p class="mt-2 text-gray-600">
                    Tem Claude, ChatGPT ou outra IA? Conecte-a à sua conta e peça direto no chat: "busque vagas de Laravel júnior
                    remotas", "adapte meu currículo para essa vaga", "o que posso melhorar?". Ela segue as mesmas regras de escrita
                    do site, e você pode desconectar quando quiser.
                </p>
                <a href="{{ auth()->check() ? route('dev') : route('register') }}" class="mt-4 inline-block text-sm text-emerald-700 font-medium hover:underline">
                    {{ auth()->check() ? 'Conectar minha IA →' : 'Crie uma conta para conectar →' }}
                </a>
            </div>
        </section>

        {{-- Perguntas frequentes --}}
        <section>
            <h2 class="text-2xl font-bold text-gray-900">Perguntas frequentes</h2>
            <div class="mt-5 space-y-3">
                @foreach ([
                    ['É grátis mesmo?', 'Sim. O '.config('app.name').' é gratuito e de código aberto. As funções de IA usam serviços gratuitos com limite diário; quem quiser mais pode conectar a própria IA.'],
                    ['De onde vêm as vagas?', 'De sites públicos de vagas, como Gupy, LinkedIn, Indeed, InfoJobs, Vagas.com, Programathor e consultorias. A coleta roda a cada 4 horas, só com vagas dos últimos dias, e a mesma vaga publicada em vários sites aparece uma vez só. A candidatura é sempre feita no anúncio original.'],
                    ['Preciso criar conta?', 'Não para ver as vagas e as estatísticas. A conta serve para montar currículos, usar a IA, acompanhar candidaturas e receber alertas.'],
                    ['E os meus dados?', 'Seguimos a LGPD: o seu currículo é só seu, telefone, e-mail e links não são enviados para a IA grátis, e você pode baixar ou apagar tudo em Minha conta quando quiser.'],
                    ['A IA inventa coisas no meu currículo?', 'Não. Ela só reorganiza e reescreve o que você já tem. Quando a vaga pede algo que não está no currículo, ela deixa um aviso amarelo "ADICIONE" para você completar, se for verdade.'],
                ] as [$question, $answer])
                    <details class="group bg-white rounded-xl border border-gray-200 px-5 py-4">
                        <summary class="cursor-pointer list-none flex items-center justify-between gap-3 font-medium text-gray-900">
                            {{ $question }}
                            <span class="text-gray-400 group-open:rotate-45 transition-transform text-xl leading-none" aria-hidden="true">+</span>
                        </summary>
                        <p class="mt-2 text-sm text-gray-600">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </section>

        <section class="text-center">
            <a href="{{ route('jobs.index') }}" class="inline-block px-6 py-3 rounded-xl bg-emerald-600 text-white font-semibold hover:bg-emerald-700">Começar pelas vagas</a>
        </section>
    </div>
</x-app-layout>

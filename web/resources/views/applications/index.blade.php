@php
    use App\Models\JobApplication;
    $sites = [
        'Plataformas' => ['LinkedIn' => 'https://www.linkedin.com/jobs/', 'Gupy' => 'https://portal.gupy.io/', 'Indeed' => 'https://br.indeed.com/',
            'Vagas.com' => 'https://www.vagas.com.br/', 'Catho' => 'https://www.catho.com.br/', 'InfoJobs' => 'https://www.infojobs.com.br/',
            'Glassdoor' => 'https://www.glassdoor.com.br/', 'Sólides Vagas' => 'https://vagas.solides.com.br/', 'Programathor' => 'https://programathor.com.br/',
            'Empregos.com.br' => 'https://www.empregos.com.br/', 'Pandapé' => 'https://www.pandape.com.br/', '99jobs' => 'https://99jobs.com/', 'Trampos.co' => 'https://trampos.co/'],
        'Consultorias' => ['Robert Half' => 'https://www.roberthalf.com/br/pt', 'Michael Page' => 'https://www.michaelpage.com.br/', 'Page Personnel' => 'https://www.pagepersonnel.com.br/',
            'Hays' => 'https://www.hays.com.br/', 'Randstad' => 'https://www.randstad.com.br/', 'Adecco' => 'https://www.adecco.com/pt-br',
            'ManpowerGroup' => 'https://www.manpowergroup.com.br/', 'Gi Group' => 'https://br.gigroup.com/', 'Cia de Talentos' => 'https://www.ciadetalentos.com.br/'],
        'Públicos e estágio' => ['Emprega Brasil (SINE)' => 'https://empregabrasil.mte.gov.br/', 'CIEE' => 'https://portal.ciee.org.br/', 'IEL' => 'https://www.iel.org.br/'],
    ];
@endphp
<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 grid lg:grid-cols-[1fr_280px] gap-8">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-gray-900">Candidaturas</h1>
            <p class="text-gray-500 mt-1 text-sm">Acompanhe onde você se candidatou. Sem resposta há mais de {{ JobApplication::FOLLOW_UP_DAYS }} dias? Hora de mandar um follow-up.</p>

            @if (session('status'))
                <div class="mt-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('status') }}</div>
            @endif

            <div class="mt-5 flex flex-wrap gap-2 text-sm">
                <a href="{{ route('applications.index') }}" @class(['px-3 py-1 rounded-full border', 'bg-gray-900 text-white border-gray-900' => ! $status, 'border-gray-300 text-gray-600' => $status])>Todas ({{ $counts->sum() }})</a>
                @foreach (JobApplication::STATUSES as $key => $label)
                    @if ($counts[$key] ?? 0)
                        <a href="?status={{ $key }}" @class(['px-3 py-1 rounded-full border', 'bg-gray-900 text-white border-gray-900' => $status === $key, 'border-gray-300 text-gray-600' => $status !== $key])>{{ $label }} ({{ $counts[$key] }})</a>
                    @endif
                @endforeach
            </div>

            <details class="mt-5 bg-white rounded-xl border border-gray-200 p-4">
                <summary class="cursor-pointer text-sm font-medium text-gray-800">+ Registrar candidatura feita em outro site</summary>
                <form method="POST" action="{{ route('applications.store') }}" class="mt-4 grid sm:grid-cols-2 gap-3">
                    @csrf
                    <input name="title" required placeholder="Vaga *" class="rounded-lg border-gray-300 text-sm">
                    <input name="company" placeholder="Empresa" class="rounded-lg border-gray-300 text-sm">
                    <input name="url" type="url" placeholder="Link da vaga" class="sm:col-span-2 rounded-lg border-gray-300 text-sm">
                    <button class="sm:col-span-2 justify-self-start px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700">Registrar</button>
                </form>
                <x-input-error :messages="$errors->all()" class="mt-2" />
            </details>

            <div class="mt-5 space-y-3">
                @forelse ($applications as $app)
                    <div x-data="{ edit: false }" @class(['bg-white rounded-xl border p-4', 'border-amber-300' => $app->alert() === 'follow_up', 'border-red-300' => $app->alert() === 'no_response', 'border-gray-200' => ! $app->alert()])>
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900">
                                    @if ($app->url)<a href="{{ $app->url }}" target="_blank" rel="noopener nofollow" class="hover:underline">{{ $app->title }}</a>@else{{ $app->title }}@endif
                                </p>
                                <p class="text-sm text-gray-500">{{ $app->company ?? 'Empresa não informada' }} · enviado em {{ $app->applied_at->format('d/m/Y') }}</p>
                            </div>
                            <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-700 text-xs">{{ JobApplication::STATUSES[$app->status] }}</span>
                        </div>
                        @if ($app->alert() === 'follow_up')
                            <p class="mt-2 text-sm text-amber-700">{{ $app->daysWithoutResponse() }} dias sem retorno: mande uma mensagem ao recrutador.</p>
                        @elseif ($app->alert() === 'no_response')
                            <p class="mt-2 text-sm text-red-600">{{ $app->daysWithoutResponse() }} dias sem retorno: considere marcar como "Sem retorno".</p>
                        @endif
                        @if ($app->next_step)<p class="mt-2 text-sm text-gray-700"><strong>Próximo passo:</strong> {{ $app->next_step }}</p>@endif
                        @if ($app->notes)<p class="mt-1 text-sm text-gray-500 whitespace-pre-line">{{ $app->notes }}</p>@endif

                        <button @click="edit = !edit" class="mt-3 text-sm text-emerald-700 hover:underline">Atualizar</button>
                        <div x-show="edit" x-cloak class="mt-3 flex flex-col gap-2">
                            <form method="POST" action="{{ route('applications.update', $app) }}" class="grid sm:grid-cols-3 gap-2">
                                @csrf @method('PATCH')
                                <select name="status" class="rounded-lg border-gray-300 text-sm">
                                    @foreach (JobApplication::STATUSES as $key => $label)
                                        <option value="{{ $key }}" @selected($app->status === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input name="next_step" value="{{ $app->next_step }}" placeholder="Próximo passo (ex.: entrevista 05/12 14h)" class="sm:col-span-2 rounded-lg border-gray-300 text-sm">
                                <textarea name="notes" rows="2" placeholder="Observações" class="sm:col-span-3 rounded-lg border-gray-300 text-sm">{{ $app->notes }}</textarea>
                                <button class="justify-self-start px-4 py-2 rounded-lg bg-gray-900 text-white text-sm">Salvar</button>
                            </form>
                            <form method="POST" action="{{ route('applications.destroy', $app) }}" onsubmit="return confirm('Remover esta candidatura?')">
                                @csrf @method('DELETE')
                                <button class="text-sm text-red-600 hover:underline">Remover</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-center text-gray-500 py-12">Nenhuma candidatura ainda. Abra uma vaga e clique em "Me candidatei".</p>
                @endforelse
            </div>
        </div>

        <aside class="text-sm">
            <h2 class="font-semibold text-gray-900">Cadastre seu currículo também em</h2>
            @foreach ($sites as $group => $links)
                <h3 class="mt-4 text-xs uppercase tracking-wide text-gray-400">{{ $group }}</h3>
                <ul class="mt-1 space-y-1">
                    @foreach ($links as $name => $url)
                        <li><a href="{{ $url }}" target="_blank" rel="noopener" class="text-gray-700 hover:text-emerald-700">{{ $name }} &nearr;</a></li>
                    @endforeach
                </ul>
            @endforeach
        </aside>
    </div>
</x-app-layout>

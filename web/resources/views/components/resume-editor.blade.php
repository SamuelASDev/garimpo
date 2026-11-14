@props(['config'])
{{-- Editor de currículo estilo Word. $config: html, saveUrl, importUrl?, wordUrl, filename, match?, placeholders?, recommendations? --}}
<div data-resume-editor x-data="resumeEditor(@js($config))" class="grid lg:grid-cols-[1fr_320px] gap-6 items-start">
    <div class="min-w-0">
        <div class="sticky top-0 z-10 bg-gray-100/95 backdrop-blur py-2 print:hidden">
            <div class="flex flex-wrap items-center gap-1 bg-white border border-gray-200 rounded-xl p-1.5 shadow-sm text-sm">
                @php($btn = 'h-8 min-w-8 px-2 rounded-md hover:bg-gray-100 text-gray-700 disabled:opacity-40')
                <button type="button" class="{{ $btn }}" @click="cmd('undo')" title="Desfazer (Ctrl+Z)">↶</button>
                <button type="button" class="{{ $btn }}" @click="cmd('redo')" title="Refazer (Ctrl+Y)">↷</button>
                <span class="w-px h-6 bg-gray-200 mx-1"></span>
                <button type="button" class="{{ $btn }}" :class="state.h1 && 'bg-gray-200'" @click="cmd('toggleHeading', { level: 1 })" title="Nome (título grande)">Nome</button>
                <button type="button" class="{{ $btn }}" :class="state.h2 && 'bg-gray-200'" @click="cmd('toggleHeading', { level: 2 })" title="Título de seção">Seção</button>
                <button type="button" class="{{ $btn }}" @click="cmd('setParagraph')" title="Texto normal">Texto</button>
                <select class="h-8 rounded-md border-gray-200 text-xs py-0" :value="state.fontSize" @change="setFontSize($event.target.value)" title="Tamanho da fonte">
                    <option value="">Tamanho</option>
                    @foreach (['8pt', '9pt', '10pt', '11pt', '12pt', '14pt', '16pt', '20pt'] as $size)
                        <option value="{{ $size }}">{{ (int) $size }}</option>
                    @endforeach
                </select>
                <span class="w-px h-6 bg-gray-200 mx-1"></span>
                <button type="button" class="{{ $btn }} font-bold" :class="state.bold && 'bg-gray-200'" @click="cmd('toggleBold')" title="Negrito (Ctrl+B)">B</button>
                <button type="button" class="{{ $btn }} italic" :class="state.italic && 'bg-gray-200'" @click="cmd('toggleItalic')" title="Itálico (Ctrl+I)">I</button>
                <button type="button" class="{{ $btn }} underline" :class="state.underline && 'bg-gray-200'" @click="cmd('toggleUnderline')" title="Sublinhado (Ctrl+U)">U</button>
                <button type="button" class="{{ $btn }} line-through" :class="state.strike && 'bg-gray-200'" @click="cmd('toggleStrike')" title="Tachado">S</button>
                <label class="{{ $btn }} inline-flex items-center cursor-pointer" title="Cor do texto">A<input type="color" class="w-4 h-4 ms-0.5 border-0 p-0" @input="cmd('setColor', $event.target.value)"></label>
                <button type="button" class="{{ $btn }}" :class="state.mark && 'bg-yellow-200'" @click="cmd('toggleHighlight')" title="Marca-texto">🖍</button>
                <span class="w-px h-6 bg-gray-200 mx-1"></span>
                <button type="button" class="{{ $btn }}" :class="state.bullet && 'bg-gray-200'" @click="cmd('toggleBulletList')" title="Lista">• —</button>
                <button type="button" class="{{ $btn }}" :class="state.ordered && 'bg-gray-200'" @click="cmd('toggleOrderedList')" title="Lista numerada">1.</button>
                <button type="button" class="{{ $btn }}" :class="state.left && 'bg-gray-200'" @click="cmd('setTextAlign', 'left')" title="Alinhar à esquerda">⇤</button>
                <button type="button" class="{{ $btn }}" :class="state.center && 'bg-gray-200'" @click="cmd('setTextAlign', 'center')" title="Centralizar">↔</button>
                <button type="button" class="{{ $btn }}" :class="state.right && 'bg-gray-200'" @click="cmd('setTextAlign', 'right')" title="Alinhar à direita">⇥</button>
                <button type="button" class="{{ $btn }}" :class="state.justify && 'bg-gray-200'" @click="cmd('setTextAlign', 'justify')" title="Justificar">☰</button>
                <button type="button" class="{{ $btn }}" @click="cmd('unsetAllMarks')" title="Limpar formatação">⌫</button>

                <div class="ms-auto flex flex-wrap gap-1">
                    @isset($config['importUrl'])
                        <label class="h-8 px-3 inline-flex items-center rounded-md border border-gray-300 hover:bg-gray-50 cursor-pointer" :class="busy && 'opacity-50 pointer-events-none'">
                            📄 Importar PDF<input type="file" accept="application/pdf" class="hidden" @change="importPdf($event)">
                        </label>
                    @endisset
                    <button type="button" class="h-8 px-3 rounded-md border border-gray-300 hover:bg-gray-50" @click="exportPdf()">⬇ PDF</button>
                    <button type="button" class="h-8 px-3 rounded-md border border-gray-300 hover:bg-gray-50" @click="exportWord()">⬇ Word</button>
                    <button type="button" class="h-8 px-4 rounded-md bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50" :disabled="busy" @click="save()" @keydown.ctrl.s.window.prevent="save()">
                        <span x-text="busy ? 'Aguarde…' : (dirty ? 'Salvar •' : 'Salvo ✓')"></span>
                    </button>
                </div>
            </div>
            <p x-show="message" x-cloak x-transition x-text="message" class="mt-2 px-3 py-2 rounded-lg bg-gray-900 text-white text-sm"></p>
        </div>

        <div class="mt-3 overflow-x-auto">
            <div class="resume-page" x-ref="page"></div>
        </div>
    </div>

    <aside class="space-y-4 print:hidden lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto">
        {{ $slot }}

        {{-- Sugestões da IA (remover / adicionar / ajustar) --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-sm" x-show="recommendations.length || suggestUrl" x-cloak>
            <div class="flex items-center justify-between gap-2">
                <p class="font-semibold text-gray-900">🤖 Sugestões da IA</p>
                <button type="button" x-show="recommendations.some(r => r.tipo === 'remover' && !r.applied)" @click="applyAll()" class="px-2.5 py-1 rounded-md bg-gray-900 text-white text-xs hover:bg-gray-700">Fazer tudo</button>
            </div>
            <button type="button" x-show="suggestUrl" @click="loadSuggestions()" :disabled="suggesting"
                    class="mt-3 w-full px-3 py-2 rounded-lg border border-emerald-300 text-emerald-700 hover:bg-emerald-50 disabled:opacity-60"
                    x-text="suggesting ? 'Analisando seu currículo…' : (recommendations.length ? '↻ Analisar de novo' : '✨ O que posso melhorar?')"></button>
            <ul class="mt-3 space-y-3" x-show="recommendations.length">
                <template x-for="(r, i) in recommendations" :key="i">
                    <li class="rounded-lg border p-3" :class="r.applied ? 'border-emerald-200 bg-emerald-50 opacity-70' : 'border-gray-200'">
                        <span class="text-[11px] font-semibold uppercase px-1.5 py-0.5 rounded"
                              :class="{ 'bg-red-100 text-red-700': r.tipo === 'remover', 'bg-sky-100 text-sky-700': r.tipo === 'adicionar', 'bg-violet-100 text-violet-700': r.tipo === 'ajustar' }" x-text="r.tipo"></span>
                        <p class="mt-1.5 text-gray-800" x-text="r.trecho"></p>
                        <p class="mt-1 text-xs text-gray-500" x-text="r.motivo"></p>
                        <div class="mt-2 flex gap-3 text-xs" x-show="!r.applied">
                            <button type="button" x-show="r.tipo === 'remover'" @click="applyRecommendation(r)" class="text-red-600 hover:underline">Remover do currículo</button>
                            <button type="button" x-show="r.tipo !== 'remover'" @click="askAi('Aplique esta sugestão no meu currículo, sem inventar dados (se faltar algo, me pergunte): ' + r.trecho); r.applied = true" class="text-emerald-700 hover:underline">Pedir para a IA aplicar</button>
                        </div>
                        <p x-show="r.applied" class="mt-2 text-xs text-emerald-700">✓ Feito</p>
                        <p x-show="r.notFound && !r.applied" class="mt-2 text-xs text-gray-400">Trecho não encontrado (talvez já editado).</p>
                    </li>
                </template>
            </ul>
        </div>

        {{-- Chat com a IA: altera o currículo ou responde dúvidas --}}
        <div class="bg-white rounded-xl border border-gray-200 text-sm flex flex-col" x-show="assistantUrl" x-cloak>
            <div class="px-5 pt-4 pb-2">
                <p class="font-semibold text-gray-900">💬 Peça para a IA</p>
                <p class="text-xs text-gray-500">Ela edita o documento para você. Ex.: <em>"adicione que usei Docker na Empresa X para fazer deploy"</em>.</p>
            </div>
            <div class="px-5 space-y-2 max-h-72 overflow-y-auto" x-ref="chatLog">
                <template x-for="(m, i) in chat" :key="i">
                    <div :class="m.role === 'user' ? 'ms-8 bg-emerald-600 text-white' : 'me-8 bg-gray-100 text-gray-800'" class="rounded-xl px-3 py-2 whitespace-pre-line" x-text="m.text"></div>
                </template>
                <div x-show="chatBusy" class="me-8 bg-gray-100 text-gray-500 rounded-xl px-3 py-2">Pensando…</div>
            </div>
            <div class="px-5 pt-2 flex flex-wrap gap-1.5" x-show="!chat.length">
                <template x-for="s in ['Que melhorias você sugere?', 'O que falta para passar no ATS?', 'Deixe as atividades mais objetivas']" :key="s">
                    <button type="button" @click="askAi(s)" class="px-2 py-1 rounded-full border border-gray-200 text-xs text-gray-600 hover:bg-gray-50" x-text="s"></button>
                </template>
            </div>
            <button type="button" x-show="undoHtml" @click="undoAi()" class="mx-5 mt-2 text-xs text-amber-700 hover:underline text-left">↶ Desfazer a última mudança da IA</button>
            <form class="p-3 flex gap-2" @submit.prevent="askAi(chatInput)">
                <textarea x-model="chatInput" rows="2" maxlength="2000" placeholder="Escreva o que a IA deve fazer…" @keydown.enter.prevent="if (!$event.shiftKey) askAi(chatInput)"
                          class="flex-1 rounded-lg border-gray-300 text-sm resize-none"></textarea>
                <button class="px-3 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50" :disabled="chatBusy || !chatInput.trim()" title="Enviar">➤</button>
            </form>
            <p class="px-5 pb-3 text-[11px] text-gray-400">IA grátis: 40 mensagens por dia. Com <a href="{{ route('ai.show') }}" class="underline">sua própria IA</a>, sem limite.</p>
        </div>
    </aside>
</div>

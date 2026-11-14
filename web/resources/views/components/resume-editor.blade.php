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

    <aside class="space-y-4 print:hidden lg:sticky lg:top-4">
        {{ $slot }}
    </aside>
</div>

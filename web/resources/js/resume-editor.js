import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';
import Highlight from '@tiptap/extension-highlight';
import { TextStyle, Color, FontSize } from '@tiptap/extension-text-style';

const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
const normalize = (s) => s.toLowerCase().replace(/\s+/g, ' ').trim();

async function postJson(url, method, body) {
    const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(body),
    });
    if (!res.ok) throw new Error(friendlyError(res.status, await res.json().catch(() => ({}))));
    return res.json();
}

/** Mensagem em português para o usuário (nunca detalhe técnico do servidor). */
function friendlyError(status, data = {}) {
    if (status === 419) return 'Sua sessão expirou. Recarregue a página (o que você já salvou continua lá).';
    if (status === 401) return 'Você saiu da conta. Entre de novo para continuar.';
    if (status === 429) return 'Muitas ações em pouco tempo. Espere um minutinho e tente de novo.';
    if (status === 422) return Object.values(data.errors || {})[0]?.[0] || data.message || 'Confira os dados e tente de novo.';
    if (status >= 500) return 'Tivemos um problema do nosso lado. Tente de novo em alguns instantes.';
    return data.message || 'Não deu certo. Tente de novo.';
}

/** Lê o texto de um PDF no navegador (pdf.js), mantendo as quebras de linha. */
async function pdfToText(file) {
    const pdfjs = await import('pdfjs-dist');
    pdfjs.GlobalWorkerOptions.workerSrc = (await import('pdfjs-dist/build/pdf.worker.min.mjs?url')).default;
    const pdf = await pdfjs.getDocument({ data: await file.arrayBuffer() }).promise;
    let text = '';
    for (let n = 1; n <= pdf.numPages; n++) {
        const content = await (await pdf.getPage(n)).getTextContent();
        text += content.items.map((i) => i.str + (i.hasEOL ? '\n' : '')).join('') + '\n\n';
    }
    return text.trim();
}

export function registerResumeEditor(Alpine) {
    Alpine.data('resumeEditor', ({ html, saveUrl, importUrl = null, wordUrl, pdfUrl, filename, match = null, placeholders = [], recommendations = [], assistantUrl = null, suggestUrl = null }) => {
        let editor = null; // fora do estado reativo do Alpine (o TipTap não funciona dentro de proxy)

        return {
            dirty: false,
            busy: false,
            message: '',
            match,
            placeholders,
            recommendations: recommendations.map((r) => ({ ...r, applied: false, notFound: false })),
            state: {},
            assistantUrl,
            suggestUrl,
            suggesting: false,
            chat: [],
            chatInput: '',
            chatBusy: false,
            undoHtml: null,
            pages: 1,

            init() {
                editor = new Editor({
                    element: this.$refs.page,
                    extensions: [StarterKit, TextAlign.configure({ types: ['heading', 'paragraph'] }), Highlight, TextStyle, Color, FontSize],
                    content: html,
                    onUpdate: () => { this.dirty = true; this.measurePages(); },
                    onTransaction: () => { this.refreshState(); },
                });
                this.refreshState();
                this.$nextTick(() => this.measurePages());
                window.addEventListener('resize', () => this.measurePages());
                window.addEventListener('beforeunload', (e) => { if (this.dirty) e.preventDefault(); });
            },

            refreshState() {
                this.state = {
                    bold: editor.isActive('bold'), italic: editor.isActive('italic'), underline: editor.isActive('underline'),
                    strike: editor.isActive('strike'), h1: editor.isActive('heading', { level: 1 }), h2: editor.isActive('heading', { level: 2 }),
                    bullet: editor.isActive('bulletList'), ordered: editor.isActive('orderedList'), mark: editor.isActive('highlight'),
                    left: editor.isActive({ textAlign: 'left' }), center: editor.isActive({ textAlign: 'center' }),
                    right: editor.isActive({ textAlign: 'right' }), justify: editor.isActive({ textAlign: 'justify' }),
                    fontSize: editor.getAttributes('textStyle').fontSize || '',
                };
            },

            /** Executa um comando da barra de ferramentas: cmd('toggleBold'), cmd('setTextAlign', 'center')... */
            cmd(name, ...args) {
                editor.chain().focus()[name](...args).run();
            },

            setFontSize(size) {
                size ? this.cmd('setFontSize', size) : this.cmd('unsetFontSize');
            },

            async save() {
                this.busy = true;
                try {
                    const data = await postJson(saveUrl, 'PUT', { html: editor.getHTML() });
                    this.dirty = false;
                    this.flash(data.message);
                    if (data.match) this.match = data.match;
                    if (data.placeholders) this.placeholders = data.placeholders;
                } catch (e) {
                    this.flash('Não foi possível salvar: ' + e.message);
                } finally {
                    this.busy = false;
                }
            },

            async importPdf(event) {
                const file = event.target.files[0];
                event.target.value = '';
                if (!file) return;
                if (this.dirty && !confirm('Importar vai substituir o que está no editor. Continuar?')) return;
                this.busy = true;
                this.flash('Lendo o PDF e organizando no modelo… pode levar alguns segundos.');
                try {
                    const text = await pdfToText(file);
                    if (text.length < 50) throw new Error('não encontrei texto no PDF (ele pode ser uma imagem escaneada).');
                    const data = await postJson(importUrl, 'POST', { text });
                    editor.commands.setContent(data.html);
                    this.dirty = true;
                    this.flash(data.message);
                } catch (e) {
                    this.flash('Não deu para importar: ' + e.message);
                } finally {
                    this.busy = false;
                }
            },

            /** Baixa o currículo como arquivo (.pdf ou .docx) gerado no servidor. */
            download(url) {
                const form = Object.assign(document.createElement('form'), { method: 'POST', action: url });
                for (const [name, value] of Object.entries({ _token: csrf(), html: editor.getHTML(), filename })) {
                    form.append(Object.assign(document.createElement('input'), { type: 'hidden', name, value }));
                }
                document.body.append(form);
                form.submit();
                form.remove();
            },

            exportPdf() {
                this.download(pdfUrl);
            },

            exportWord() {
                this.download(wordUrl);
            },

            /**
             * Quantas folhas A4 o conteúdo ocupa. A página do editor cresce de folha em folha e mostra onde
             * cada uma termina (nada é cortado; o PDF/Word quebram nos mesmos pontos, aproximadamente).
             */
            measurePages() {
                const page = this.$refs.page;
                const content = page?.querySelector('.ProseMirror');
                if (!content) return;
                const mm = page.offsetWidth / 210; // a folha tem 210mm de largura (menor no celular: escala junto)
                const usable = (297 - 32) * mm;    // altura útil: A4 menos as margens de cima e de baixo
                this.pages = Math.max(1, Math.ceil((content.scrollHeight - 2) / usable));
            },

            /** Recomendação "ajustar" com texto novo: troca o trecho antigo pelo novo, mantendo a formatação. */
            replaceRecommendation(rec) {
                const target = normalize(rec.trecho);
                let range = null;
                // 1º: o trecho exato dentro de um texto (mantém negrito/cor ao redor).
                editor.state.doc.descendants((node, pos) => {
                    if (range || !node.isText) return !range;
                    const i = node.text.indexOf(rec.trecho);
                    if (i >= 0) range = { from: pos + i, to: pos + i + rec.trecho.length };
                    return false;
                });
                // 2º: o parágrafo/tópico que contém o trecho (texto quebrado em partes com formatações diferentes).
                if (!range) {
                    editor.state.doc.descendants((node, pos) => {
                        if (range) return false;
                        if (node.type.name === 'paragraph' && normalize(node.textContent).includes(target)) {
                            range = { from: pos + 1, to: pos + node.nodeSize - 1 };
                            return false;
                        }
                        return true;
                    });
                }
                if (!range) {
                    rec.notFound = true;
                    return false;
                }
                editor.chain().focus().insertContentAt(range, rec.novo).run();
                rec.applied = true;
                this.flash('Trecho trocado. Revise e clique em Salvar.');
                return true;
            },

            /** Recomendação "remover": apaga o item (tópico ou parágrafo) que contém o trecho. */
            applyRecommendation(rec) {
                const target = normalize(rec.trecho);
                let range = null;
                editor.state.doc.descendants((node, pos) => {
                    if (range) return false;
                    if (['listItem', 'paragraph'].includes(node.type.name) && normalize(node.textContent).includes(target)) {
                        range = { from: pos, to: pos + node.nodeSize };
                        return false;
                    }
                    return true;
                });
                if (!range) {
                    rec.notFound = true;
                    return false;
                }
                editor.chain().focus().deleteRange(range).run();
                rec.applied = true;
                return true;
            },

            applyAll() {
                const done = this.recommendations.filter((r) => r.tipo === 'remover' && !r.applied).filter((r) => this.applyRecommendation(r)).length;
                this.flash(done ? `${done} trecho(s) removido(s). Revise e clique em Salvar.` : 'Nada para remover.');
            },

            /** Chat: a IA responde e, se for o caso, devolve o documento alterado (dá para desfazer). */
            async askAi(text) {
                text = (text || '').trim();
                if (!text || this.chatBusy) return;
                this.chat.push({ role: 'user', text });
                this.chatInput = '';
                this.chatBusy = true;
                this.scrollChat();
                try {
                    const res = await fetch(assistantUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                        body: JSON.stringify({ message: text, html: editor.getHTML() }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok && !data.reply) throw new Error(friendlyError(res.status, data));
                    if (data.html) {
                        this.undoHtml = editor.getHTML();
                        editor.commands.setContent(data.html, { emitUpdate: true });
                        this.dirty = true;
                    }
                    this.chat.push({ role: 'ai', text: (data.reply || data.message || 'Não consegui responder agora.') + (data.html ? '\n\n✏️ Alterei o documento. Revise e clique em Salvar.' : '') });
                } catch (e) {
                    this.chat.push({ role: 'ai', text: 'Não consegui falar com a IA: ' + e.message });
                } finally {
                    this.chatBusy = false;
                    this.scrollChat();
                }
            },

            undoAi() {
                editor.commands.setContent(this.undoHtml, { emitUpdate: true });
                this.undoHtml = null;
                this.dirty = true;
                this.flash('Mudança da IA desfeita.');
            },

            scrollChat() {
                this.$nextTick(() => { this.$refs.chatLog.scrollTop = this.$refs.chatLog.scrollHeight; });
            },

            /** Sugestões gerais para o currículo principal. */
            async loadSuggestions() {
                if (this.dirty) await this.save();
                this.suggesting = true;
                try {
                    const data = await postJson(suggestUrl, 'POST', { pages: this.pages });
                    this.recommendations = data.recommendations.map((r) => ({ ...r, applied: false, notFound: false }));
                    if (!this.recommendations.length) this.flash('A IA não encontrou melhorias importantes. 👏');
                } catch (e) {
                    this.flash(e.message);
                } finally {
                    this.suggesting = false;
                }
            },

            flash(text) {
                this.message = text;
                clearTimeout(this._t);
                this._t = setTimeout(() => { this.message = ''; }, 7000);
            },

            scoreColor() {
                const s = this.match?.score ?? 0;
                return s >= 70 ? '#059669' : s >= 40 ? '#f59e0b' : '#ef4444';
            },
        };
    });
}

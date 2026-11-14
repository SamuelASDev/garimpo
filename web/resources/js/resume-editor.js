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
    if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || 'Erro ' + res.status);
    return res.json();
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
    Alpine.data('resumeEditor', ({ html, saveUrl, importUrl = null, wordUrl, pdfUrl, filename, match = null, placeholders = [], recommendations = [] }) => {
        let editor = null; // fora do estado reativo do Alpine (o TipTap não funciona dentro de proxy)

        return {
            dirty: false,
            busy: false,
            message: '',
            match,
            placeholders,
            recommendations: recommendations.map((r) => ({ ...r, applied: false, notFound: false })),
            state: {},

            init() {
                editor = new Editor({
                    element: this.$refs.page,
                    extensions: [StarterKit, TextAlign.configure({ types: ['heading', 'paragraph'] }), Highlight, TextStyle, Color, FontSize],
                    content: html,
                    onUpdate: () => { this.dirty = true; },
                    onTransaction: () => { this.refreshState(); },
                });
                this.refreshState();
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

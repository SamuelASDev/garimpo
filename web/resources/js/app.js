import Alpine from 'alpinejs';

window.Alpine = Alpine;

// O editor (TipTap + pdf.js) só é baixado nas páginas que têm currículo para editar.
if (document.querySelector('[data-resume-editor]')) {
    const { registerResumeEditor } = await import('./resume-editor');
    registerResumeEditor(Alpine);
}

Alpine.start();

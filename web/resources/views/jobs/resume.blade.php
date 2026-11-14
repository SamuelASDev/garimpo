<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
            <a href="{{ route('jobs.show', $job) }}" class="text-sm text-gray-500 hover:text-gray-800">&larr; {{ $job->title }}</a>
            <button onclick="window.print()" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700">Salvar como PDF</button>
        </div>
        <p class="mt-2 text-xs text-gray-400 print:hidden">Gerado por {{ $tailoring->generated_by }}. Revise antes de enviar: a IA pode errar.</p>

        <article class="mt-4 bg-white border border-gray-200 rounded-xl p-8 print:border-0 print:p-0 whitespace-pre-line text-[15px] leading-relaxed text-gray-900 font-[Arial,sans-serif]">{{ str_replace(['**', '##', '# '], '', $tailoring->content) }}</article>
    </div>
</x-app-layout>

@props(['job'])
@php
    $labels = \App\Services\JobClassifier::LABELS;
    $mode = [
        'remoto' => ['🏠 Remoto', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        'hibrido' => ['🔀 Híbrido', 'bg-violet-50 text-violet-700 ring-violet-200'],
        'presencial' => ['🏢 Presencial', 'bg-slate-100 text-slate-700 ring-slate-200'],
    ][$job->work_mode] ?? ['Modalidade não informada', 'bg-white text-gray-400 ring-gray-200 border-dashed'];
@endphp
{{-- Senioridade, área e modalidade sempre visíveis; quando o anúncio não diz, mostra "não identificada". --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-1.5 text-xs font-medium']) }}>
    @if ($job->seniority)
        <span class="px-2 py-1 rounded-full ring-1 bg-amber-50 text-amber-700 ring-amber-200">🎚️ {{ $labels[$job->seniority] }}</span>
    @else
        <span class="px-2 py-1 rounded-full ring-1 bg-white text-gray-400 ring-gray-200">Senioridade não identificada</span>
    @endif
    @if ($job->area)
        <span class="px-2 py-1 rounded-full ring-1 bg-sky-50 text-sky-700 ring-sky-200">🧭 {{ $labels[$job->area] ?? $job->area }}</span>
    @else
        <span class="px-2 py-1 rounded-full ring-1 bg-white text-gray-400 ring-gray-200">Área não identificada</span>
    @endif
    <span class="px-2 py-1 rounded-full ring-1 {{ $mode[1] }}">{{ $mode[0] }}</span>
</div>

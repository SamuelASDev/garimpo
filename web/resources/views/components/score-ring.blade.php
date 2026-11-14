@props(['score' => 0, 'size' => 96])
@php
    $color = $score >= 70 ? '#059669' : ($score >= 40 ? '#f59e0b' : '#ef4444');
    $label = $score >= 70 ? 'Ótimo match!' : ($score >= 40 ? 'Dá para chegar lá' : 'Precisa de ajustes');
@endphp
{{-- Medidor circular de compatibilidade com a vaga --}}
<div {{ $attributes->merge(['class' => 'flex items-center gap-4']) }}>
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 36 36" class="shrink-0 -rotate-90">
        <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e5e7eb" stroke-width="3.2" />
        <circle cx="18" cy="18" r="15.9" fill="none" stroke="{{ $color }}" stroke-width="3.2" stroke-linecap="round" stroke-dasharray="{{ $score }} 100" />
        <text x="18" y="21" text-anchor="middle" font-size="9" font-weight="700" fill="{{ $color }}" transform="rotate(90 18 18)">{{ $score }}%</text>
    </svg>
    <div>
        <p class="font-semibold" style="color: {{ $color }}">{{ $label }}</p>
        <p class="text-sm text-gray-500">{{ $slot }}</p>
    </div>
</div>

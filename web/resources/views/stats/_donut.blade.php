@php
    $sum = max(1, array_sum($data));
    $stops = [];
    $start = 0;
    foreach (array_values($data) as $i => $n) {
        $end = $start + $n / $sum * 100;
        $stops[] = $palette[$i % count($palette)].' '.round($start, 2).'% '.round($end, 2).'%';
        $start = $end;
    }
@endphp
<div class="bg-white rounded-xl border border-gray-200 p-4">
    <h2 class="font-semibold text-gray-900">{{ $title }}</h2>
    <div class="mt-3 flex items-center gap-5">
        <div class="relative w-32 h-32 shrink-0 rounded-full" style="background: conic-gradient({{ implode(', ', $stops) ?: '#e5e7eb 0 100%' }})">
            <div class="absolute inset-6 rounded-full bg-white flex items-center justify-center text-sm font-semibold text-gray-700">{{ array_sum($data) }}</div>
        </div>
        <ul class="space-y-1 text-sm min-w-0">
            @foreach ($data as $name => $n)
                <li class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-sm shrink-0" style="background: {{ $palette[$loop->index % count($palette)] }}"></span>
                    <span class="truncate text-gray-700">{{ $name }}</span>
                    <span class="text-gray-500 tabular-nums">{{ $n }} ({{ round($n / $sum * 100) }}%)</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>

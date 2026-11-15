@php $max = max(1, ...array_values($data ?: [0])); @endphp
<div class="bg-white rounded-xl border border-gray-200 p-4">
    <h2 class="font-semibold text-gray-900">{{ $title }}</h2>
    <div class="mt-3 space-y-1.5">
        @forelse ($data as $name => $n)
            <div class="flex items-center gap-2 text-sm">
                <span class="w-36 shrink-0 truncate text-gray-700" title="{{ $name }}">
                    @isset($link)
                        <a href="{{ route('jobs.index', [$link => $name]) }}" class="hover:underline">{{ $name }}</a>
                    @else
                        {{ $name }}
                    @endisset
                </span>
                <div class="flex-1 h-4 bg-gray-100 rounded">
                    <div class="h-4 rounded" style="width: {{ $n / $max * 100 }}%; background: {{ $color }}"></div>
                </div>
                <span class="w-10 text-right text-gray-500 tabular-nums">{{ $n }}</span>
            </div>
        @empty
            <p class="text-sm text-gray-500">Sem dados.</p>
        @endforelse
    </div>
</div>

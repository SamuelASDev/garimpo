@props(['city' => null, 'state' => null])
@php
    $states = \App\Services\Locations::STATES;
    // Cidade escolhida antes: mostra a UF dela de novo ("São Paulo — SP").
    $cityState = $city && ! $state ? \App\Models\JobPosting::where('city_key', \App\Services\Locations::key($city))->whereNotNull('state')->value('state') : null;
    $label = $city ? $city.(($state ?: $cityState) ? ' — '.($state ?: $cityState) : '') : ($state && isset($states[$state]) ? 'Todo o estado: '.$states[$state].' ('.$state.')' : '');
@endphp
{{-- Campo de cidade com sugestões: "São Paulo — SP · 54 vagas". Envia city (cidade) e/ou state (UF). --}}
<div class="relative" x-data="{
        label: @js($label), city: @js($city ?? ''), state: @js($state ?? ''),
        items: [], open: false, active: -1, timer: null,
        search() {
            this.city = this.label.split(' — ')[0].replace(/^Todo o estado: /, ''); this.state = '';
            clearTimeout(this.timer);
            if (this.label.trim().length < 2) { this.items = []; this.open = false; return; }
            this.timer = setTimeout(async () => {
                try {
                    const res = await fetch('{{ route('cities') }}?q=' + encodeURIComponent(this.label.trim()), { headers: { Accept: 'application/json' } });
                    this.items = res.ok ? await res.json() : [];
                } catch (e) { this.items = []; }
                this.active = this.items.length ? 0 : -1;
                this.open = true;
            }, 200);
        },
        pick(item) {
            this.city = item.city ?? '';
            this.state = item.type === 'state' ? item.state : '';
            this.label = item.type === 'state' ? 'Todo o estado: ' + item.name + ' (' + item.state + ')' : item.city + (item.state ? ' — ' + item.state : '');
            this.open = false;
        },
        move(step) { if (this.items.length) { this.active = (this.active + step + this.items.length) % this.items.length; } },
        clear() { this.label = ''; this.city = ''; this.state = ''; this.items = []; this.open = false; },
    }" @click.outside="open = false">
    <input type="hidden" name="city" :value="city" :disabled="!city">
    <input type="hidden" name="state" :value="state" :disabled="!state">
    <input type="text" x-model="label" @input="search()" @focus="items.length && (open = true)"
           @keydown.arrow-down.prevent="open = true; move(1)" @keydown.arrow-up.prevent="move(-1)"
           @keydown.enter="if (open && items[active]) { $event.preventDefault(); pick(items[active]); }"
           @keydown.escape="open = false"
           placeholder="📍 Cidade ou estado" autocomplete="off" role="combobox" :aria-expanded="open"
           class="w-full rounded-lg border-gray-300 text-sm pr-8">
    <button type="button" x-show="label" x-cloak @click="clear()" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700" aria-label="Limpar cidade">✕</button>

    <ul x-show="open" x-cloak x-transition role="listbox"
        class="absolute z-30 mt-1 w-full min-w-[16rem] bg-white border border-gray-200 rounded-xl shadow-lg py-1 max-h-72 overflow-y-auto text-sm">
        <template x-for="(item, i) in items" :key="item.type + (item.city ?? '') + item.state">
            <li role="option" @mousedown.prevent="pick(item)" @mouseenter="active = i"
                :class="i === active ? 'bg-emerald-50' : ''" class="px-3 py-2 cursor-pointer flex items-center justify-between gap-3">
                <span class="min-w-0 truncate">
                    <template x-if="item.type === 'city'">
                        <span><span class="text-gray-900" x-text="item.city"></span> <span class="text-gray-400" x-text="item.state ? '— ' + item.state : ''"></span></span>
                    </template>
                    <template x-if="item.type === 'state'">
                        <span class="text-gray-700">Todo o estado: <strong x-text="item.name"></strong> <span class="text-gray-400" x-text="'(' + item.state + ')'"></span></span>
                    </template>
                </span>
                <span class="shrink-0 text-xs text-gray-400" x-text="item.total + (item.total === 1 ? ' vaga' : ' vagas')"></span>
            </li>
        </template>
        <li x-show="!items.length" class="px-3 py-2 text-gray-500">Nenhuma cidade com vagas com esse nome.</li>
    </ul>
</div>

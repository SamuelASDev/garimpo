<nav x-data="{ open: false }" class="bg-white border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center gap-8">
                <a href="{{ route('jobs.index') }}" class="flex items-center gap-2 font-bold text-gray-900">
                    <x-application-logo class="w-8 h-8" />
                    {{ config('app.name') }}
                </a>
                <div class="hidden sm:flex gap-6 h-full">
                    <x-nav-link :href="route('jobs.index')" :active="request()->routeIs('jobs.*')">Vagas</x-nav-link>
                    <x-nav-link :href="route('stats')" :active="request()->routeIs('stats')">Estatísticas</x-nav-link>
                    @auth
                        <x-nav-link :href="route('resumes.index')" :active="request()->routeIs('resume.*', 'resumes.*', 'jobs.resume')">Currículos</x-nav-link>
                        <x-nav-link :href="route('applications.index')" :active="request()->routeIs('applications.*')">Candidaturas</x-nav-link>
                        <x-nav-link :href="route('alerts.index')" :active="request()->routeIs('alerts.*')">Alertas</x-nav-link>
                        <x-nav-link :href="route('ai.show')" :active="request()->routeIs('ai.*')">Conectar IA</x-nav-link>
                        @can('admin')
                            <x-nav-link :href="route('admin.index')" :active="request()->routeIs('admin.*')">Admin</x-nav-link>
                        @endcan
                    @endauth
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center gap-4 text-sm">
                @auth
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center gap-1 px-3 py-2 rounded-md text-gray-600 hover:text-gray-900">
                                {{ Auth::user()->name }}
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.3 7.3a1 1 0 011.4 0L10 10.6l3.3-3.3a1 1 0 111.4 1.4l-4 4a1 1 0 01-1.4 0l-4-4a1 1 0 010-1.4z" clip-rule="evenodd" /></svg>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">Minha conta</x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Sair</x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @else
                    <a href="{{ route('login') }}" class="text-gray-600 hover:text-gray-900">Entrar</a>
                    <a href="{{ route('register') }}" class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Criar conta</a>
                @endauth
            </div>

            <div class="flex items-center sm:hidden">
                <button @click="open = ! open" class="p-2 rounded-md text-gray-500 hover:bg-gray-100" aria-label="Menu">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-gray-200 pb-3">
        <x-responsive-nav-link :href="route('jobs.index')" :active="request()->routeIs('jobs.*')">Vagas</x-responsive-nav-link>
        <x-responsive-nav-link :href="route('stats')" :active="request()->routeIs('stats')">Estatísticas</x-responsive-nav-link>
        @auth
            <x-responsive-nav-link :href="route('resumes.index')" :active="request()->routeIs('resume.*', 'resumes.*', 'jobs.resume')">Currículos</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('applications.index')" :active="request()->routeIs('applications.*')">Candidaturas</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('alerts.index')" :active="request()->routeIs('alerts.*')">Alertas</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('ai.show')" :active="request()->routeIs('ai.*')">Conectar IA</x-responsive-nav-link>
            @can('admin')
                <x-responsive-nav-link :href="route('admin.index')" :active="request()->routeIs('admin.*')">Admin</x-responsive-nav-link>
            @endcan
            <x-responsive-nav-link :href="route('profile.edit')">Minha conta</x-responsive-nav-link>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Sair</x-responsive-nav-link>
            </form>
        @else
            <x-responsive-nav-link :href="route('login')">Entrar</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('register')">Criar conta</x-responsive-nav-link>
        @endauth
    </div>
</nav>

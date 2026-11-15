<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @include('admin._nav')

        <form class="mt-6 flex gap-2 text-sm">
            <input name="q" value="{{ request('q') }}" placeholder="Nome ou e-mail" class="rounded-lg border-gray-300 text-sm">
            <button class="px-4 py-2 rounded-lg bg-gray-900 text-white">Buscar</button>
            <span class="self-center text-gray-500">{{ $users->total() }} contas</span>
        </form>

        <div class="mt-4 bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            @foreach ($users as $user)
                <div class="p-3 flex flex-wrap items-center justify-between gap-3 text-sm">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900">
                            {{ $user->name }}
                            @if ($user->is_admin) <span class="ml-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs">admin</span> @endif
                        </p>
                        <p class="text-gray-500">
                            {{ $user->email }} · {{ $user->hasVerifiedEmail() ? 'e-mail confirmado' : 'e-mail não confirmado' }} ·
                            criada {{ $user->created_at->diffForHumans() }} · {{ $user->job_applications_count }} candidaturas · {{ $user->job_alerts_count }} alertas
                        </p>
                    </div>
                    @unless ($user->is(auth()->user()))
                        <div class="flex items-center gap-3">
                            <form method="POST" action="{{ route('admin.users.admin', $user) }}">
                                @csrf @method('PATCH')
                                <button class="text-gray-700 hover:underline">{{ $user->is_admin ? 'Tirar admin' : 'Tornar admin' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Apagar a conta de {{ $user->email }} e todos os dados dela? Não dá para desfazer.')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Apagar conta</button>
                            </form>
                        </div>
                    @endunless
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $users->links() }}</div>
    </div>
</x-app-layout>

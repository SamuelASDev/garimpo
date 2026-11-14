<x-guest-layout>
    <h2 class="text-2xl font-bold text-gray-900">Crie sua conta grátis</h2>
    <p class="mt-1 mb-8 text-sm text-gray-500">Leva menos de um minuto. Depois é só importar seu currículo.</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <!-- LGPD: aceite dos termos (obrigatório) e consentimento para a IA grátis (opcional) -->
        <div class="mt-5 space-y-3 text-sm text-gray-600">
            <label class="flex items-start gap-2">
                <input type="checkbox" name="terms" value="1" required @checked(old('terms')) class="mt-0.5 rounded border-gray-300 text-emerald-600">
                <span>Li e aceito os <a href="{{ route('legal.terms') }}" target="_blank" class="underline">Termos de Uso</a> e a <a href="{{ route('legal.privacy') }}" target="_blank" class="underline">Política de Privacidade</a>.</span>
            </label>
            <x-input-error :messages="$errors->get('terms')" />
            <label class="flex items-start gap-2">
                <input type="checkbox" name="ai_consent" value="1" @checked(old('ai_consent')) class="mt-0.5 rounded border-gray-300 text-emerald-600">
                <span>(Opcional) Autorizo usar a IA grátis do Garimpo: o texto do meu currículo, <strong>sem telefone, e-mail e links de perfil</strong>, é enviado a provedores de IA fora do Brasil (Groq, Google, OpenRouter) para gerar e melhorar currículos. Posso desligar quando quiser.</span>
            </label>
        </div>

        <x-primary-button class="mt-6 w-full justify-center py-3">{{ __('Register') }}</x-primary-button>

        <p class="mt-6 text-center text-sm text-gray-600">{{ __('Already registered?') }} <a href="{{ route('login') }}" class="font-medium text-emerald-700 hover:underline">Entrar</a></p>
    </form>
</x-guest-layout>

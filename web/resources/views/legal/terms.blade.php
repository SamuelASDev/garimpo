<x-app-layout>
    <article class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-sm leading-relaxed text-gray-700 space-y-5 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-gray-900 [&_h2]:mt-8 [&_ul]:list-disc [&_ul]:ps-5 [&_ul]:space-y-1">
        <h1 class="text-2xl font-bold text-gray-900">Termos de Uso</h1>
        <p class="text-gray-500">Última atualização: 14/11/2026</p>

        <h2>1. O que é o {{ config('app.name') }}</h2>
        <p>Um projeto open source e gratuito que reúne vagas de tecnologia publicadas em outros sites, compara com o seu currículo e ajuda
            a adaptá-lo. Não somos agência de empregos, não intermediamos contratações e não garantimos resultado em processos seletivos.</p>

        <h2>2. Vagas</h2>
        <ul>
            <li>As vagas pertencem aos sites e empresas de origem; a candidatura é sempre feita no anúncio original.</li>
            <li>Podemos exibir vagas desatualizadas ou com erros de classificação. Confira no anúncio original.</li>
            <li>Nunca pague para se candidatar. Desconfie de vagas que pedem PIX, taxas ou cursos obrigatórios.</li>
        </ul>

        <h2>3. Seu currículo e a IA</h2>
        <ul>
            <li>Você é responsável pela veracidade do que coloca no currículo. A IA é instruída a não inventar experiências, mas pode errar: revise sempre antes de enviar.</li>
            <li>A IA grátis tem limites diários e pode ficar indisponível. Ela só é usada com a sua autorização (veja a Política de Privacidade).</li>
            <li>Ao conectar sua própria IA, você responde pelo que ela fizer na sua conta. Desconecte na Seção dev quando quiser.</li>
        </ul>

        <h2>4. Uso correto</h2>
        <p>Não use o site para enviar spam, coletar dados de outras pessoas, sobrecarregar o serviço ou violar a lei. Contas que fizerem isso podem ser suspensas.</p>

        <h2>5. Privacidade</h2>
        <p>O tratamento dos seus dados segue a <a href="{{ route('legal.privacy') }}" class="underline">Política de Privacidade</a>.</p>

        <h2>6. Código aberto</h2>
        <p>O código do {{ config('app.name') }} é distribuído sob a licença MIT. Este serviço é oferecido "como está", sem garantias.</p>

        <h2>7. Contato</h2>
        <p>{{ config('garimpo.privacy_email') }}. Estes termos seguem as leis brasileiras.</p>
    </article>
</x-app-layout>

<x-app-layout>
    <article class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-sm leading-relaxed text-gray-700 space-y-5 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-gray-900 [&_h2]:mt-8 [&_ul]:list-disc [&_ul]:ps-5 [&_ul]:space-y-1">
        <h1 class="text-2xl font-bold text-gray-900">Política de Privacidade</h1>
        <p class="text-gray-500">Última atualização: {{ \Illuminate\Support\Carbon::parse('2026-11-14')->format('d/m/Y') }}</p>

        <p>O {{ config('app.name') }} é um projeto open source que reúne vagas de TI e ajuda você a adaptar seu currículo.
            Esta política explica, de acordo com a Lei Geral de Proteção de Dados (Lei 13.709/2018 – LGPD), quais dados
            pessoais tratamos, por quê e quais são os seus direitos.</p>

        <h2>1. Quem é o responsável (controlador)</h2>
        <p>{{ config('garimpo.controller') }}. Para qualquer assunto sobre seus dados, inclusive exercer seus direitos,
            escreva para <strong>{{ config('garimpo.privacy_email') }}</strong>.</p>

        <h2>2. Quais dados tratamos e para quê</h2>
        <ul>
            <li><strong>Conta</strong> (nome, e-mail, senha guardada com criptografia de mão única): para você entrar e usar o site. Base legal: execução de contrato (art. 7º, V).</li>
            <li><strong>Currículos</strong> (o principal e os adaptados por vaga), incluindo o que você escrever neles: para comparar com vagas, gerar e exportar currículos. Base legal: execução de contrato (art. 7º, V).</li>
            <li><strong>Candidaturas</strong> (vaga, empresa, status, observações): para você acompanhar seus processos. Base legal: execução de contrato.</li>
            <li><strong>Registros técnicos</strong> (endereço IP, navegador, data de acesso na sessão): para segurança e para cumprir o Marco Civil da Internet. Base legal: obrigação legal e legítimo interesse (art. 7º, II e IX).</li>
            <li><strong>Conexões de IA</strong> (nome da conexão, app, datas): para controlar quem acessa sua conta pelo MCP.</li>
            <li><strong>Alertas de vagas</strong> (filtros escolhidos e data do último envio): para mandar, no máximo uma vez por dia, um e-mail com vagas novas. Só enviamos para e-mail confirmado, e todo e-mail tem o link "Parar de receber". Base legal: execução de contrato, a seu pedido.</li>
        </ul>
        <p>Não pedimos dados sensíveis (art. 5º, II). Evite colocar no currículo CPF, RG, estado civil, religião, saúde ou foto: eles não ajudam no processo seletivo.</p>

        <h2>3. Uso de inteligência artificial e transferência internacional</h2>
        <p>A <strong>IA grátis</strong> do site só é usada se você autorizar (no cadastro ou em Minha conta). Nesse caso, o texto do currículo
            e da vaga é enviado a provedores de IA com servidores fora do Brasil: <strong>Groq</strong> (EUA), <strong>Google Gemini</strong> (EUA)
            e <strong>OpenRouter</strong> (EUA). Antes do envio, o Garimpo <strong>remove telefone, e-mail, CPF e links de perfil</strong> e os recoloca
            só na resposta. A camada gratuita de alguns provedores pode usar o conteúdo para melhorar seus modelos.
            Base legal: seu consentimento específico (art. 7º, I e art. 33, VIII), que pode ser retirado a qualquer momento.</p>
        <p>Se você conectar <strong>sua própria IA</strong> (Claude, ChatGPT, Codex…), é você quem autoriza esse app a acessar seus dados;
            o tratamento feito por ele segue a política do provedor que você escolheu. Você pode desconectar na Seção dev.</p>

        <h2>4. Vagas exibidas</h2>
        <p>As vagas vêm de páginas públicas de sites de emprego, sempre com link para o anúncio original. Removemos e-mails e telefones
            de pessoas que aparecem nas descrições; para falar com o recrutador, use o anúncio original.</p>

        <h2>5. Com quem compartilhamos</h2>
        <p>Não vendemos nem compartilhamos seus dados para publicidade. Só há compartilhamento com: os provedores de IA acima (se autorizado),
            o provedor de hospedagem do site e as IAs que você mesmo conectar.</p>

        <h2>6. Por quanto tempo guardamos</h2>
        <p>Enquanto sua conta existir. Ao excluir a conta, apagamos currículos, candidaturas e conexões de IA na hora.
            Registros de acesso podem ser mantidos por até 6 meses, como exige o Marco Civil da Internet (art. 15).</p>

        <h2>7. Seus direitos (art. 18)</h2>
        <ul>
            <li><strong>Acessar e levar seus dados:</strong> em Minha conta → "Baixar meus dados" (arquivo JSON).</li>
            <li><strong>Corrigir:</strong> edite a qualquer momento seu perfil, currículos e candidaturas.</li>
            <li><strong>Retirar o consentimento da IA grátis:</strong> em Minha conta → Privacidade.</li>
            <li><strong>Excluir tudo:</strong> em Minha conta → "Excluir conta".</li>
            <li><strong>Saber com quem compartilhamos, pedir informações ou reclamar:</strong> pelo e-mail acima. Você também pode recorrer à ANPD (gov.br/anpd).</li>
        </ul>

        <h2>8. Segurança</h2>
        <p>Senhas com hash, acesso só por login, conexões de IA com token revogável e com validade, e HTTPS no site publicado.
            Nenhum sistema é 100% seguro; se houver incidente que traga risco a você, avisaremos e comunicaremos a ANPD (art. 48).</p>

        <h2>9. Cookies</h2>
        <p>Usamos apenas cookies essenciais (sessão e proteção contra fraude em formulários). Não usamos cookies de publicidade ou rastreamento.</p>

        <h2>10. Crianças e adolescentes</h2>
        <p>O site não é destinado a crianças. Adolescentes (jovem aprendiz, estágio) devem usá-lo com ciência dos pais ou responsáveis.</p>

        <h2>11. Mudanças</h2>
        <p>Se esta política mudar de forma relevante, avisaremos no site antes de a mudança valer.</p>
    </article>
</x-app-layout>

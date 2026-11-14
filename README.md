# Garimpo ⛏️

Garimpar vaga não precisa ser no braço. O **Garimpo** junta vagas de TI de vários sites num lugar só
(desenvolvimento, suporte, infra, dados, QA…), classifica cada uma por senioridade, área e tecnologia,
e mostra o quanto o **seu currículo** combina com ela, do jeito que um ATS lê.

- 🔎 Vagas de várias fontes, sem repetição, com link direto para a vaga original
- 🏷️ Filtros por senioridade, área, tecnologia e remoto (classificação por regras, sem IA)
- 📄 Score ATS: o que você já tem e o que falta no currículo para cada vaga
- 🔔 Alertas por e-mail: monte filtros e receba as vagas novas 1 vez por dia
- 🤖 Currículo adaptado com IA grátis (Gemini / Groq), sem inventar experiência

## Estrutura

| Pasta | O que é |
|---|---|
| `web/` | Site em Laravel 13 + Breeze (Blade + Tailwind) |
| `collector/` | Coletor em Python (só biblioteca padrão) que envia vagas para `POST /api/ingest` |
| `.github/workflows/collect.yml` | Roda o coletor a cada 4 h no GitHub Actions |

## Rodando localmente

```bash
cd web
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve --port=8010
```

Localmente usa SQLite (não precisa de MySQL). Em produção, troque `DB_CONNECTION=mysql` no `.env`.

Coletar vagas (com o site rodando):

```bash
INGEST_URL=http://127.0.0.1:8010/api/ingest INGEST_TOKEN=<o mesmo do .env> python collector/collect.py
```

## Configuração (`web/.env`)

| Variável | Para quê |
|---|---|
| `INGEST_TOKEN` | Senha que o coletor usa para enviar vagas |
| `GROQ_API_KEY` | chave grátis em console.groq.com (IA principal, mais rápida) |
| `GEMINI_API_KEY` | (opcional) chave grátis em aistudio.google.com, reserva |
| `OPENROUTER_API_KEY` | (opcional) chave grátis em openrouter.ai, segunda reserva |

No GitHub Actions, configure os *secrets* `INGEST_URL`, `INGEST_TOKEN` e, se quiser,
`JOOBLE_API_KEY` (grátis, 100 chamadas/dia; a chave vale para um país, informe o host na
*variable* `JOOBLE_HOST`, padrão `br.jooble.org`) e `ADZUNA_APP_ID` / `ADZUNA_APP_KEY` (pago). Para incluir LinkedIn e Indeed via `python-jobspy`,
crie a *variable* `USE_JOBSPY=1`.

## Conectar a própria IA (MCP)

O endpoint `/mcp` aceita login por **OAuth** (Laravel Passport): no Claude.ai, ChatGPT, Claude Code, Codex
ou Cursor basta colar `https://seu-dominio/mcp` e autorizar. Também dá para gerar um token manual em **Conectar IA**.
A IA ganha ferramentas para buscar vagas, ler e adaptar currículos e gerenciar candidaturas.

No servidor (uma vez): extensão PHP `sodium` ligada e

```bash
php artisan migrate
php artisan passport:keys
php artisan passport:client --personal --name=Garimpo --provider=users
```

As chaves ficam em `storage/oauth-*.key` (fora do Git). Domínios aceitos no login ficam em `config/mcp.php`.

## E-mail e alertas diários

Os alertas de vagas, a confirmação de conta e a redefinição de senha usam o e-mail do `.env`
(na Hostinger, crie uma conta em hPanel → E-mails):

```
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=alertas@seu-dominio
MAIL_PASSWORD=senha-dessa-conta
MAIL_FROM_ADDRESS=alertas@seu-dominio
```

Teste com `php artisan garimpo:testar-email voce@exemplo.com`. O resumo dos alertas sai às 8h (Brasília), no máximo
1 e-mail por pessoa por dia, só para e-mails confirmados. Para isso, crie no hPanel → Avançado → Cron um job a cada minuto:

```
cd /caminho/do/site && php artisan schedule:run >> /dev/null 2>&1
```

## Produção: segurança e LGPD

No `.env` do servidor:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio
SESSION_SECURE_COOKIE=true
GARIMPO_CONTROLLER="Nome de quem responde pelos dados"
GARIMPO_PRIVACY_EMAIL=contato-de-privacidade@seu-dominio
```

- Instale sem pacotes de desenvolvimento: `composer install --no-dev --optimize-autoloader`.
- Site só em HTTPS (em produção o Garimpo envia HSTS).
- Privacidade: política em `/privacidade`, termos em `/termos`, aceite registrado no cadastro, IA grátis só com
  consentimento (contatos são mascarados antes do envio), exportação em Minha conta e exclusão completa da conta.

## Fontes de vagas

Gupy, Programathor, Vagas.com, InfoJobs, Empregos.com.br, Robert Half, Michael Page e Randstad
(páginas públicas de busca), LinkedIn e Indeed via
[python-jobspy](https://github.com/speedyapply/JobSpy), Jooble (API oficial grátis) e Adzuna (API oficial, paga).
A mesma vaga publicada em vários sites entra uma vez só (hash de título + empresa + local).
O Garimpo guarda só um resumo e sempre leva para a vaga original.

## Licença

[MIT](LICENSE)

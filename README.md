# Garimpo ⛏️

Garimpar vaga não precisa ser no braço. O **Garimpo** junta vagas de TI de vários sites num lugar só
(desenvolvimento, suporte, infra, dados, QA…), classifica cada uma por senioridade, área e tecnologia,
e mostra o quanto o **seu currículo** combina com ela, do jeito que um ATS lê.

- 🔎 Vagas de várias fontes, sem repetição, com link direto para a vaga original
- 🏷️ Filtros por senioridade, área, tecnologia e remoto (classificação por regras, sem IA)
- 📄 Score ATS: o que você já tem e o que falta no currículo para cada vaga
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

Em **Conectar IA** o usuário gera um token e conecta Claude Code, Cursor, Codex etc. ao endpoint `/mcp`.
A IA ganha ferramentas para buscar vagas, ler o currículo, gerar currículos adaptados e gerenciar candidaturas.

## Fontes de vagas

Gupy, Programathor, Vagas.com e InfoJobs (páginas públicas de busca), LinkedIn e Indeed via
[python-jobspy](https://github.com/speedyapply/JobSpy), Jooble (API oficial grátis) e Adzuna (API oficial, paga).
A mesma vaga publicada em vários sites entra uma vez só (hash de título + empresa + local).
O Garimpo guarda só um resumo e sempre leva para a vaga original.

## Licença

[MIT](LICENSE)

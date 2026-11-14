# Garimpo ⛏️

**Garimpe as vagas certas. Chegue no ATS pronto.**

O Garimpo junta vagas de TI de vários sites num lugar só (desenvolvimento, suporte, infraestrutura, dados, QA…),
mostra o quanto **o seu currículo** combina com cada uma, do jeito que um sistema de ATS lê, e ajuda a montar
um currículo adaptado para cada vaga, sem inventar nada.

Projeto open source (licença MIT), feito em Laravel + um coletor em Python.

---

## Sumário

- [O que ele faz](#o-que-ele-faz)
- [Como funciona por dentro](#como-funciona-por-dentro)
- [Instalação local (passo a passo)](#instalação-local-passo-a-passo)
- [Configuração (`.env`)](#configuração-env)
- [Coletor de vagas](#coletor-de-vagas)
- [Testes](#testes)
- [Publicar na Hostinger (ou outra hospedagem compartilhada)](#publicar-na-hostinger-ou-outra-hospedagem-compartilhada)
- [Coleta automática (GitHub Actions)](#coleta-automática-github-actions)
- [Conectar a própria IA (MCP)](#conectar-a-própria-ia-mcp)
- [Atualizar o site depois](#atualizar-o-site-depois)
- [Privacidade (LGPD) e segurança](#privacidade-lgpd-e-segurança)
- [Problemas comuns](#problemas-comuns)
- [Licença](#licença)

---

## O que ele faz

- 🔎 **Vagas de vários sites, sem repetição**: Gupy, LinkedIn, Indeed, Vagas.com, InfoJobs, Empregos.com.br,
  Programathor, Robert Half, Michael Page e Randstad. A mesma vaga em sites diferentes aparece uma vez só, sempre
  com link para o anúncio original.
- 🏷️ **Classificação automática** (sem IA): senioridade, área, tecnologias, competências técnicas
  (hardware, redes, atendimento…) e comportamentais. Filtros por tudo isso, inclusive várias tecnologias juntas.
- 🎯 **Score ATS**: quanto do que a vaga pede está no seu currículo, o que você já tem e o que falta.
- 📝 **Editor de currículo estilo Word**, num modelo ATS de uma coluna: importa PDF, exporta PDF e Word.
- ✨ **Currículo por vaga**: a IA adapta o seu currículo principal para a vaga, marca em amarelo o que falta e
  sugere o que remover, ajustar ou adicionar. Tem também um chat que edita o documento por você.
- 📋 **Controle de candidaturas** com status e alerta de follow-up (15 e 30 dias sem retorno).
- 🔔 **Alertas por e-mail**: monte filtros e receba as vagas novas no máximo 1 vez por dia.
- 🤖 **Conectar a própria IA** (Claude, ChatGPT, Codex, Cursor) por MCP, com login OAuth.
- 🔒 **LGPD**: consentimento para IA, contatos mascarados antes de ir para a IA, exportar e apagar seus dados.

## Como funciona por dentro

```
GitHub Actions (a cada 4h)                       Seu servidor (Laravel)
┌──────────────────────────┐   POST /api/ingest  ┌───────────────────────────────────┐
│ collector/collect.py     │ ──────────────────▶ │ filtra TI, remove duplicadas,     │
│ busca vagas nos sites    │   (INGEST_TOKEN)    │ classifica e salva no banco       │
└──────────────────────────┘                     │                                   │
                                                 │ site: vagas, currículos, editor,  │
     IA grátis (Groq/OpenRouter/Gemini) ◀─────── │ candidaturas, alertas, /mcp       │
     IA do usuário (Claude, Codex…) ──── MCP ──▶ └───────────────────────────────────┘
```

| Pasta | O que é |
|---|---|
| `web/` | O site: Laravel 13, Blade, Tailwind, Alpine, editor TipTap |
| `collector/` | Coletor em Python (só biblioteca padrão; `python-jobspy` opcional para LinkedIn/Indeed) |
| `.github/workflows/collect.yml` | Roda o coletor a cada 4 horas |

## Instalação local (passo a passo)

### 1. Pré-requisitos

| Programa | Versão | Observação |
|---|---|---|
| PHP | **8.4 ou mais novo** | Com as extensões `sodium`, `openssl`, `mbstring`, `xml`, `fileinfo`, `pdo_sqlite` (ou `pdo_mysql`) |
| Composer | 2.x | |
| Node.js | 20.19+ ou 22.12+ | Só para compilar CSS/JS |
| Python | 3.10+ | Só se for rodar o coletor |
| Git | qualquer | |

> **Windows com XAMPP:** a extensão `sodium` costuma vir desligada. Abra `C:\xampp\php\php.ini`,
> procure `;extension=sodium` e tire o `;` do começo. Confira com `php -m` (tem que aparecer `sodium`).

### 2. Baixar e instalar

```bash
git clone https://github.com/SamuelASDev/garimpo.git
cd garimpo/web
composer install
npm install
npm run build
```

### 3. Configurar

```bash
cp .env.example .env
php artisan key:generate
```

Por padrão o projeto usa **SQLite** (um arquivo, sem precisar de MySQL). Crie o banco e as chaves do login OAuth:

```bash
php artisan migrate
php artisan passport:keys
php artisan passport:client --personal --name=Garimpo --provider=users
```

### 4. Rodar

```bash
php artisan serve --port=8010
```

Abra **http://localhost:8010**. Use outra porta se a 8010 estiver ocupada (e ajuste `APP_URL` no `.env`).

### 5. (Opcional) Conta de teste e vagas de verdade

Crie uma conta de teste já com currículo de exemplo. Antes, defina `TEST_USER_PASSWORD` no `.env`:

```bash
php artisan db:seed --class=TestUserSeeder
```

Para ter vagas, rode o coletor apontando para o seu site local (veja [Coletor de vagas](#coletor-de-vagas)).

## Configuração (`.env`)

As principais variáveis (todas estão comentadas no `web/.env.example`):

| Variável | Para quê | Obrigatória? |
|---|---|---|
| `APP_URL` | Endereço do site (ex.: `http://localhost:8010`) | Sim |
| `DB_CONNECTION` | `sqlite` (local) ou `mysql` (servidor) | Sim |
| `INGEST_TOKEN` | Senha que o coletor usa para enviar vagas ao site. Gere com `php -r "echo bin2hex(random_bytes(24));"` | Sim |
| `GROQ_API_KEY` | IA grátis principal: chave em [console.groq.com](https://console.groq.com) → API Keys | Para usar a IA grátis |
| `OPENROUTER_API_KEY` | IA grátis reserva: [openrouter.ai](https://openrouter.ai) → Keys | Não |
| `GEMINI_API_KEY` | IA grátis reserva: [aistudio.google.com](https://aistudio.google.com) → Get API key | Não |
| `MAIL_*` | E-mail para alertas, confirmação de conta e senha (veja [E-mail](#7-e-mail-alertas-e-confirmação-de-conta)) | Para enviar e-mails |
| `GARIMPO_CONTROLLER` / `GARIMPO_PRIVACY_EMAIL` | Quem responde pelos dados e o e-mail de privacidade (aparecem na Política de Privacidade) | Em produção |
| `TEST_USER_EMAIL` / `TEST_USER_PASSWORD` | Conta de teste local (`TestUserSeeder`) | Não |

A IA grátis tenta na ordem **Groq → OpenRouter → Gemini** e só é usada por quem autorizou (LGPD). Sem nenhuma
chave, o site funciona normalmente, só sem as funções de IA.

## Coletor de vagas

O coletor (`collector/collect.py`) busca as vagas e envia para `POST /api/ingest` do site. O site descarta o que
não é de TI, remove duplicadas, formata a descrição e classifica.

Rodar manualmente contra o site local (Git Bash / Linux / macOS):

```bash
INGEST_URL=http://127.0.0.1:8010/api/ingest INGEST_TOKEN=o-mesmo-do-.env python collector/collect.py
```

Só ver quantas vagas acharia, sem enviar: acrescente `DRY_RUN=1`.

| Variável do coletor | Para quê |
|---|---|
| `INGEST_URL` | `https://seu-site/api/ingest` |
| `INGEST_TOKEN` | Igual ao `INGEST_TOKEN` do `.env` do site |
| `USE_JOBSPY=1` | Inclui LinkedIn e Indeed (instale antes: `pip install python-jobspy`) |
| `JOOBLE_API_KEY` / `JOOBLE_HOST` | Jooble (grátis, 100 chamadas/dia; a chave vale para um país, padrão `br.jooble.org`) |
| `ADZUNA_APP_ID` / `ADZUNA_APP_KEY` | Adzuna (API paga) |

Ficam de fora: Catho e Glassdoor (bloqueiam robôs) e sites que só carregam vagas por JavaScript
(99jobs, Trampos, Sólides, Hays, Gi Group).

## Testes

```bash
cd web
php artisan test
```

## Publicar na Hostinger (ou outra hospedagem compartilhada)

Funciona no plano de hospedagem compartilhada (Premium/Business), com SSH.

### 1. Preparar no hPanel

1. **Subdomínio**: hPanel → Domínios → Subdomínios → crie (ex.: `garimpo.seudominio.com.br`).
   Na pasta do subdomínio, use um caminho como `public_html/garimpo/web/public` (o site é servido pela pasta `public`).
2. **PHP**: hPanel → Avançado → Configuração do PHP → versão **8.4 ou mais nova**, com `sodium` ligado.
3. **Banco MySQL**: hPanel → Bancos de dados → crie banco, usuário e senha.
4. **SSL**: ative o certificado gratuito para o subdomínio (o site precisa de HTTPS).
5. **SSH**: hPanel → Avançado → Acesso SSH → ative e anote host, porta e usuário.

### 2. Baixar o código no servidor

Pelo SSH:

```bash
cd ~/domains/seudominio.com.br/public_html
git clone https://github.com/SamuelASDev/garimpo.git garimpo
cd garimpo/web
composer install --no-dev --optimize-autoloader
```

### 3. CSS e JavaScript

A pasta `public/build` não vai para o Git. Gere no seu computador e envie para o servidor:

```bash
cd web
npm run build
```

Depois envie a pasta `web/public/build` para `garimpo/web/public/build` no servidor (Gerenciador de Arquivos do
hPanel ou SFTP). Se o seu plano tiver Node.js no SSH, pode rodar `npm ci && npm run build` direto no servidor.

### 4. Configurar o `.env` de produção

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

Ajuste pelo menos:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://garimpo.seudominio.com.br
SESSION_SECURE_COOKIE=true

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=nome_do_banco
DB_USERNAME=usuario_do_banco
DB_PASSWORD=senha_do_banco

INGEST_TOKEN=gere-uma-senha-nova-e-longa
GROQ_API_KEY=sua-chave

GARIMPO_CONTROLLER="Seu nome ou do projeto"
GARIMPO_PRIVACY_EMAIL=privacidade@seudominio.com.br
```

### 5. Banco, OAuth e caches

```bash
php artisan migrate --force
php artisan passport:keys
php artisan passport:client --personal --name=Garimpo --provider=users
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Abra o site no navegador: a lista de vagas (vazia por enquanto) deve aparecer.

### 6. Tarefas agendadas (cron)

hPanel → Avançado → **Cron** → novo job **a cada minuto**:

```
cd /home/SEU_USUARIO/domains/seudominio.com.br/public_html/garimpo/web && php artisan schedule:run >> /dev/null 2>&1
```

É ele que dispara o resumo diário dos alertas (8h de Brasília).

### 7. E-mail (alertas e confirmação de conta)

Crie uma conta em hPanel → E-mails (ex.: `alertas@seudominio.com.br`) e preencha no `.env`:

```
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=alertas@seudominio.com.br
MAIL_PASSWORD=senha-dessa-conta
MAIL_FROM_ADDRESS=alertas@seudominio.com.br
```

Depois rode `php artisan config:cache` e teste:

```bash
php artisan garimpo:testar-email voce@exemplo.com
```

## Coleta automática (GitHub Actions)

O workflow `.github/workflows/collect.yml` roda o coletor **a cada 4 horas**. Configure no repositório:
**Settings → Secrets and variables → Actions**.

| Tipo | Nome | Valor |
|---|---|---|
| Secret | `INGEST_URL` | `https://garimpo.seudominio.com.br/api/ingest` |
| Secret | `INGEST_TOKEN` | o mesmo `INGEST_TOKEN` do `.env` de produção |
| Variable | `USE_JOBSPY` | `1` para incluir LinkedIn e Indeed |
| Secret (opcional) | `JOOBLE_API_KEY` | chave da Jooble |

Para testar na hora: aba **Actions → Coletar vagas → Run workflow**. Enquanto os secrets não existem, o coletor
termina sem fazer nada (não dá erro). Para mudar a frequência, edite a linha `cron` do workflow
(acima de 4 em 4 horas não é recomendado: os sites podem bloquear).

> O GitHub pausa agendamentos de repositórios públicos sem commits há 60 dias; se acontecer, reative na aba Actions.

## Conectar a própria IA (MCP)

Em **Conectar IA**, dentro do site, a pessoa conecta Claude.ai, ChatGPT, Claude Code, Codex ou Cursor ao endereço
`https://seu-site/mcp`. O login é por **OAuth** (abre a tela "Autorizar" do Garimpo) ou por **token** com validade
escolhida (1 semana a 1 ano). Cada conexão pode ser desconectada sozinha.

A IA conectada pode buscar vagas, ler a vaga com o score, ler e adaptar currículos e gerenciar candidaturas.
Os domínios aceitos no login ficam em `web/config/mcp.php`. Claude.ai e ChatGPT só conectam com o site publicado em HTTPS.

## Atualizar o site depois

No servidor, pelo SSH:

```bash
cd ~/domains/seudominio.com.br/public_html/garimpo
git pull
cd web
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Se mudou CSS/JS, gere de novo o `public/build` no seu computador e envie.

## Privacidade (LGPD) e segurança

- Política de Privacidade em `/privacidade` e Termos em `/termos`; o aceite fica registrado no cadastro.
- A IA grátis só recebe dados de quem autorizou, e telefone, e-mail, CPF e links de perfil são mascarados antes do envio.
- Contatos pessoais de recrutadores são removidos das descrições de vagas.
- Em Minha conta: baixar todos os dados (JSON), desligar a IA e excluir a conta (apaga tudo, inclusive conexões de IA).
- HTML limpo no servidor ao salvar/exportar, cabeçalhos de segurança, limite de tentativas e senha forte em produção.
- **Nunca** coloque o `.env` ou as chaves `storage/oauth-*.key` no Git (já estão no `.gitignore`).

## Problemas comuns

| Sintoma | Solução |
|---|---|
| `composer install` reclama da versão do PHP | O projeto precisa de PHP **8.4+**. No hPanel, troque a versão do PHP. |
| Erro com `sodium` ou `lcobucci/jwt` | Ligue a extensão `sodium` (no XAMPP: tire o `;` de `extension=sodium` no `php.ini`). |
| Página sem estilo / erro "Vite manifest not found" | Falta a pasta `public/build`: rode `npm run build` e envie. |
| "Personal access client not found" ao gerar token | Rode `php artisan passport:client --personal --name=Garimpo --provider=users`. |
| Erro 500 sem detalhes em produção | Veja `web/storage/logs/laravel.log`. |
| Mudei o `.env` e nada mudou | Rode `php artisan config:cache` de novo. |
| Coletor responde 401 | O `INGEST_TOKEN` do GitHub está diferente do `.env` do servidor. |
| IA diz que está indisponível | Confira `GROQ_API_KEY` e se a pessoa autorizou a IA em Minha conta → Privacidade. |
| E-mails não chegam | Rode `php artisan garimpo:testar-email` e veja o log; confira também a caixa de spam. |

## Licença

[MIT](LICENSE). Use, modifique e distribua à vontade.

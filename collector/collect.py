"""Coletor de vagas de TI.

Busca vagas em fontes públicas e envia para o site (POST /api/ingest).
O site filtra o que não é TI, classifica e remove duplicadas.

Variáveis de ambiente:
  INGEST_URL     ex.: https://seusite.com/api/ingest
  INGEST_TOKEN   mesmo valor do INGEST_TOKEN do .env do Laravel
Fontes sempre ativas: Gupy, Programathor, Vagas.com e InfoJobs (páginas públicas).
  JOOBLE_API_KEY (opcional) chave grátis em jooble.org/api/about (100 chamadas/dia)
  JOOBLE_HOST    (opcional) país da chave: br.jooble.org (padrão), jooble.org (EUA)...
  ADZUNA_APP_ID / ADZUNA_APP_KEY   (opcional, pago: £8/mês) developer.adzuna.com
  USE_JOBSPY=1   (opcional) inclui LinkedIn e Indeed via python-jobspy
  DRY_RUN=1      só mostra quantas vagas achou, sem enviar
"""

import html
import json
import os
import re
import sys
import time
import urllib.parse
import urllib.error
import urllib.request

TERMS = [
    "desenvolvedor", "programador", "engenheiro de software", "full stack", "back-end", "front-end",
    "analista de sistemas", "suporte ti", "help desk", "infraestrutura ti", "devops", "dados",
    "qa", "segurança da informação", "mobile",
]
USER_AGENT = "GarimpoBot/1.0 (+https://github.com/SamuelASDev/garimpo)"
PAUSE = 2  # segundos entre requisições, para não sobrecarregar as fontes


SHORT_TERMS = ["desenvolvedor", "suporte tecnico", "analista de sistemas", "analista de dados", "devops"]


def get(url: str) -> str:
    req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
    with urllib.request.urlopen(req, timeout=30) as resp:
        raw = resp.read()
        charset = resp.headers.get_content_charset()
    if not charset:  # alguns sites não informam: tenta utf-8 e cai para latin-1
        try:
            return raw.decode("utf-8")
        except UnicodeDecodeError:
            charset = "latin-1"
    return raw.decode(charset, errors="replace")


def text(fragment: str | None) -> str:
    """Remove tags HTML, decodifica entidades e junta espaços."""
    if not fragment:
        return ""
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", " ", fragment))).strip()


def find(pattern: str, chunk: str) -> str:
    m = re.search(pattern, chunk, re.S)
    return text(m.group(1)) if m else ""


def programathor() -> list[dict]:
    """Programathor: só vagas de TI. Lista pública /jobs (3 primeiras páginas)."""
    jobs = []
    for page in range(1, 4):
        try:
            page_html = get(f"https://programathor.com.br/jobs?page={page}")
        except Exception as exc:
            print(f"[programathor] página {page}: {exc}", file=sys.stderr)
            break
        for chunk in page_html.split('<a href="/jobs/')[1:]:
            if "Vencida" in chunk[:3000]:
                continue
            slug = chunk.split('"', 1)[0]
            title = find(r"<h3[^>]*>(.*?)</h3>", chunk)
            if not title:
                continue
            location = find(r"fa-map-marker-alt'></i>(.*?)</span>", chunk)
            jobs.append({
                "source": "programathor",
                "url": f"https://programathor.com.br/jobs/{slug}",
                "title": title,
                "company": find(r"fa-briefcase'></i>(.*?)</span>", chunk) or None,
                "location": location or None,
                "remote": "remoto" in location.lower(),
                "description": find(r"<div class='cell-list-content-icon'>(.*?)</div>", chunk),
            })
        time.sleep(PAUSE)
    return jobs


def vagas_com() -> list[dict]:
    """Vagas.com.br: página pública de busca por termo."""
    jobs = []
    for term in SHORT_TERMS:
        try:
            page_html = get("https://www.vagas.com.br/vagas-de-" + term.replace(" ", "-"))
        except Exception as exc:
            print(f"[vagas.com] {term}: {exc}", file=sys.stderr)
            continue
        for chunk in page_html.split('<li class="vaga')[1:]:
            link = re.search(r'title="([^"]+)"[^>]*href="(/vagas/v\d+/[^"]+)"', chunk)
            if not link:
                continue
            date = re.search(r"(\d\d)/(\d\d)/(\d{4})", find(r'class="data-publicacao">(.*?)</span>', chunk))
            location = find(r'bx-map"></i>(.*?)<div', chunk)
            jobs.append({
                "source": "vagas.com",
                "url": "https://www.vagas.com.br" + link.group(2),
                "title": text(link.group(1)),
                "company": find(r'class="emprVaga">(.*?)</span>', chunk) or None,
                "location": location or None,
                "remote": "home office" in chunk.lower() or "remoto" in location.lower(),
                "description": find(r'class="nivelVaga">(.*?)</span>', chunk) + ". " + find(r'class="detalhes">\s*<p>(.*?)</p>', chunk),
                "posted_at": f"{date.group(3)}-{date.group(2)}-{date.group(1)}" if date else None,
            })
        time.sleep(PAUSE)
    return jobs


def infojobs() -> list[dict]:
    """InfoJobs: página pública de busca por termo."""
    jobs = []
    for term in SHORT_TERMS:
        try:
            page_html = get("https://www.infojobs.com.br/empregos.aspx?palabra=" + urllib.parse.quote(term))
        except Exception as exc:
            print(f"[infojobs] {term}: {exc}", file=sys.stderr)
            continue
        for chunk in page_html.split("js_vacancyLoad js_cardLink")[1:]:
            href = re.search(r'data-href="(/vaga-de-[^"]+\.aspx)"', chunk)
            title = find(r'js_vacancyTitle">(.*?)</h2>', chunk)
            if not (href and title):
                continue
            date = re.search(r'js_date" data-value="(\d{4})/(\d\d)/(\d\d)', chunk)
            location = find(r'<div class="mb-8">(.*?)<span hidden', chunk)
            jobs.append({
                "source": "infojobs",
                "url": "https://www.infojobs.com.br" + href.group(1),
                "title": title,
                "company": find(r'href="https://www\.infojobs\.com\.br/empresa-[^"]+">(.*?)<span', chunk) or None,
                "location": location or None,
                "remote": bool(re.search(r">\s*(Remoto|Home office)\s*<", chunk, re.I)),
                "description": find(r'<div class="text-medium">(.*?)</div>', chunk),
                "posted_at": f"{date.group(1)}-{date.group(2)}-{date.group(3)}" if date else None,
            })
        time.sleep(PAUSE)
    return jobs


def gupy() -> list[dict]:
    """Página pública de busca do portal Gupy (as vagas vêm no __NEXT_DATA__ do HTML)."""
    jobs = []
    for term in TERMS:
        try:
            html = get("https://portal.gupy.io/job-search/term=" + urllib.parse.quote(term))
            match = re.search(r'<script id="__NEXT_DATA__"[^>]*>(.*?)</script>', html, re.S)
            data = json.loads(match.group(1))["props"]["pageProps"]["initialJobList"]["data"]
        except Exception as exc:  # fonte fora do ar ou mudou o formato: segue com as outras
            print(f"[gupy] {term}: {exc}", file=sys.stderr)
            continue
        for j in data:
            location = ", ".join(p for p in (j.get("city"), j.get("state")) if p) or None
            jobs.append({
                "source": "gupy",
                "url": j["jobUrl"].split("?")[0],
                "title": j["name"].strip(),
                "company": j.get("careerPageName"),
                "location": location,
                "remote": j.get("workplaceType") == "remote",
                "work_mode": {"remote": "remoto", "hybrid": "hibrido", "on-site": "presencial"}.get(j.get("workplaceType")),
                "description": j.get("description") or "",
                "posted_at": j.get("publishedDate"),
            })
        time.sleep(PAUSE)
    return jobs


def empregos_com() -> list[dict]:
    """Empregos.com.br: lista pública por termo (descrição completa vem depois, pelo JSON-LD da vaga)."""
    jobs = []
    for term in SHORT_TERMS:
        try:
            page_html = get("https://www.empregos.com.br/vagas/" + term.replace(" ", "-"))
        except Exception as exc:
            print(f"[empregos] {term}: {exc}", file=sys.stderr)
            continue
        for chunk in page_html.split('id="job-card"')[1:]:
            href = re.search(r'href="(/vaga/\d+/[^"]+)"', chunk)
            title = find(r"<h2[^>]*>(.*?)</h2>", chunk)
            if not (href and title):
                continue
            company = find(r"<h3>(.*?)</h3>", chunk)
            jobs.append({
                "source": "empregos.com",
                "url": "https://www.empregos.com.br" + href.group(1),
                "title": title,
                "company": None if not company or company.endswith("...") else company,
                "location": None,
                "remote": "home office" in chunk.lower() or "remoto" in chunk.lower(),
                "description": "",
            })
        time.sleep(PAUSE)
    return jobs


def robert_half() -> list[dict]:
    """Robert Half: a lista pública já traz a descrição completa no card."""
    jobs = []
    for page in (1, 2):
        try:
            page_html = get(f"https://www.roberthalf.com/br/pt/vagas?page={page}")
        except Exception as exc:
            print(f"[roberthalf] página {page}: {exc}", file=sys.stderr)
            break
        for chunk in page_html.split("<rhcl-job-card")[1:]:
            link = re.search(r'href="(https://www\.roberthalf\.com/br/pt/vaga/[^"]+)"[^>]*>(.*?)</a>', chunk, re.S)
            if not link:
                continue
            worksite = find(r'data-subslot="worksite">(.*?)</li>', chunk).lower()
            jobs.append({
                "source": "roberthalf",
                "url": urllib.parse.quote(link.group(1), safe=":/?&=#%"),  # URL tem acentos (são-paulo)
                "title": text(link.group(2)),
                "company": "Robert Half (cliente confidencial)",
                "location": find(r'data-subslot="location">(.*?)</li>', chunk).strip(" ,") or None,
                "remote": worksite == "remote",
                "work_mode": "remoto" if "remote" in worksite else "hibrido" if "hybrid" in worksite else "presencial" if "site" in worksite else None,
                "description": text_block(html.unescape(find_raw(r'data-subslot="copy">(.*?)</li>', chunk))),
            })
        time.sleep(PAUSE)
    return jobs


def michael_page() -> list[dict]:
    """Michael Page: área de tecnologia (descrição completa vem depois, pela página da vaga)."""
    jobs = []
    for page in (0, 1):
        try:
            page_html = get(f"https://www.michaelpage.com.br/jobs/tecnologia?page={page}")
        except Exception as exc:
            print(f"[michaelpage] página {page}: {exc}", file=sys.stderr)
            break
        for chunk in page_html.split('class="job-tile')[1:]:
            link = re.search(r'<h3><a href="(/job-detail/[^"]+)"[^>]*>(.*?)</a>', chunk, re.S)
            if not link:
                continue
            location = find(r'class="job-location">(.*?)</div>', chunk)
            jobs.append({
                "source": "michaelpage",
                "url": "https://www.michaelpage.com.br" + link.group(1),
                "title": text(link.group(2)),
                "company": "Michael Page (cliente confidencial)",
                "location": location or None,
                "remote": "remot" in location.lower(),
                "description": find(r'class="job-summary">(.*?)</div>\s*</div>', chunk),
            })
        time.sleep(PAUSE)
    return jobs


def randstad() -> list[dict]:
    """Randstad: lista pública por termo (descrição completa vem depois, pelo JSON-LD da vaga)."""
    jobs = []
    for term in SHORT_TERMS:
        try:
            page_html = get("https://www.randstad.com.br/vagas/q-" + term.replace(" ", "-") + "/")
        except Exception as exc:
            print(f"[randstad] {term}: {exc}", file=sys.stderr)
            continue
        for chunk in page_html.split('class="cards__item')[1:]:
            link = re.search(r'href="(/vagas/[^"]+_\d+/)"[^>]*>(.*?)<span', chunk, re.S)
            if not link:
                continue
            meta = re.findall(r'class="cards__meta-item">(.*?)</li>', chunk, re.S)
            location = text(meta[0]) if meta else ""
            jobs.append({
                "source": "randstad",
                "url": "https://www.randstad.com.br" + link.group(1),
                "title": text(link.group(2)).capitalize(),
                "company": "Randstad (cliente)",
                "location": location.title() or None,
                "remote": "remot" in chunk.lower() or "home office" in chunk.lower(),
                "description": find(r'class="cards__description">(.*?)</div>', chunk),
            })
        time.sleep(PAUSE)
    return jobs


def find_raw(pattern: str, chunk: str) -> str:
    m = re.search(pattern, chunk, re.S)
    return m.group(1) if m else ""


def jooble() -> list[dict]:
    """API oficial da Jooble. Cada chave vale para um país (host). Limite grátis: 100 chamadas/dia."""
    key = os.getenv("JOOBLE_API_KEY")
    if not key:
        return []
    host = os.getenv("JOOBLE_HOST") or "br.jooble.org"
    jobs = []
    for term in ["desenvolvedor", "suporte ti", "analista de sistemas", "devops", "dados"]:
        body = json.dumps({"keywords": term, "page": "1"}).encode()
        req = urllib.request.Request(f"https://{host}/api/{key}", data=body, method="POST", headers={
            "Content-Type": "application/json", "User-Agent": USER_AGENT,
        })
        try:
            with urllib.request.urlopen(req, timeout=30) as resp:
                data = json.loads(resp.read().decode())["jobs"]
        except Exception as exc:
            print(f"[jooble] {term}: {exc}", file=sys.stderr)
            if isinstance(exc, urllib.error.HTTPError) and exc.code == 403:
                break  # chave sem acesso a esse país: não adianta tentar os outros termos
            continue
        for j in data:
            jobs.append({
                "source": "jooble",
                "url": j["link"],
                "title": j["title"],
                "company": j.get("company") or None,
                "location": j.get("location") or None,
                "remote": "remot" in (j.get("location", "") + j["title"]).lower(),
                "description": j.get("snippet", ""),
                "posted_at": j.get("updated") or None,
            })
        time.sleep(PAUSE)
    return jobs


def adzuna() -> list[dict]:
    """API oficial da Adzuna (Brasil). Só roda se as chaves estiverem configuradas."""
    app_id, app_key = os.getenv("ADZUNA_APP_ID"), os.getenv("ADZUNA_APP_KEY")
    if not (app_id and app_key):
        return []
    jobs = []
    for term in ["desenvolvedor", "suporte ti", "analista de sistemas", "devops", "dados"]:
        query = urllib.parse.urlencode({
            "app_id": app_id, "app_key": app_key, "what": term, "results_per_page": 50, "max_days_old": 3,
        })
        try:
            data = json.loads(get(f"https://api.adzuna.com/v1/api/jobs/br/search/1?{query}"))["results"]
        except Exception as exc:
            print(f"[adzuna] {term}: {exc}", file=sys.stderr)
            continue
        for j in data:
            jobs.append({
                "source": "adzuna",
                "url": j["redirect_url"],
                "title": j["title"],
                "company": (j.get("company") or {}).get("display_name"),
                "location": (j.get("location") or {}).get("display_name"),
                "remote": "remot" in (j.get("description", "") + j["title"]).lower(),
                "description": j.get("description", ""),
                "posted_at": j.get("created"),
            })
        time.sleep(PAUSE)
    return jobs


def jobspy() -> list[dict]:
    """LinkedIn e Indeed via python-jobspy (opcional; pode ser bloqueado pelos sites)."""
    if os.getenv("USE_JOBSPY") != "1":
        return []
    from jobspy import scrape_jobs  # pip install python-jobspy

    jobs = []
    # Foco em desenvolvimento, mas cobrindo as outras áreas de TI. hours_old=24 traz só as mais recentes
    # (a coleta roda a cada 4h, então nada se perde).
    terms = ["desenvolvedor", "programador", "desenvolvedor front-end", "desenvolvedor back-end", "desenvolvedor full stack",
             "analista de sistemas", "analista de TI", "suporte técnico TI", "analista de dados", "DevOps", "analista de QA"]
    for term in terms:
        try:
            df = scrape_jobs(site_name=["linkedin", "indeed"], search_term=term, location="Brazil",
                             country_indeed="brazil", results_wanted=25, hours_old=24,
                             linkedin_fetch_description=True)
        except Exception as exc:
            print(f"[jobspy] {term}: {exc}", file=sys.stderr)
            continue
        for r in df.fillna("").to_dict("records"):
            jobs.append({
                "source": str(r["site"]),
                "url": r["job_url"],
                "title": r["title"],
                "company": r["company"] or None,
                "location": r["location"] or None,
                "remote": bool(r.get("is_remote")),
                "description": str(r.get("description") or ""),
                "posted_at": str(r["date_posted"]) or None,
            })
        time.sleep(PAUSE)
    return jobs


# Sites cuja página de busca só traz um resumo: a descrição completa fica na página da vaga.
# Fontes que ficam só com o resumo com frequência (medido no banco): coletadas por último.
LAST_SOURCES = {"vagas.com"}

DETAIL_SOURCES = {"programathor", "vagas.com", "infojobs", "empregos.com", "randstad", "michaelpage"}


def text_block(fragment: str) -> str:
    """Como text(), mas mantém quebras de linha de parágrafos, listas e <br>."""
    fragment = re.sub(r"(?is)<(script|style|noscript|button|form|nav)\b.*?</\1>", " ", fragment)  # JSON-LD, botões, formulários
    fragment = re.sub(r"(?is)<a\b[^>]*class=\"[^\"]*\b(btn|button)[^\"]*\"[^>]*>.*?</a>", " ", fragment)  # links com cara de botão
    fragment = re.sub(r"(?i)<br\s*/?>|</p>|</li>|</h\d>|</div>", "\n", fragment)
    fragment = re.sub(r"(?i)<li[^>]*>", "\n• ", fragment)
    plain = html.unescape(re.sub(r"<[^>]+>", " ", fragment))
    lines = [re.sub(r"[ \t\xa0]+", " ", line).strip() for line in plain.splitlines()]
    return re.sub(r"\n{3,}", "\n\n", "\n".join(lines)).strip()


def jsonld_job(page: str) -> dict | None:
    """O bloco schema.org/JobPosting da página, se houver."""
    for block in re.findall(r'<script[^>]*application/ld\+json[^>]*>(.*?)</script>', page, re.S):
        try:
            data = json.loads(block, strict=False)  # alguns sites deixam quebras de linha cruas no texto
        except ValueError:
            continue
        items = data if isinstance(data, list) else data.get("@graph", [data])
        for item in items:
            if isinstance(item, dict) and item.get("@type") == "JobPosting":
                return item
    return None


def detail_description(job: dict) -> str:
    """Abre a página da vaga e devolve a descrição completa ("" se não conseguir).
    Pelo JSON-LD também completa empresa, local, data de publicação e se é remota."""
    page = get(job["url"])
    item = jsonld_job(page) or {}

    org = item.get("hiringOrganization")
    if not job.get("company") and isinstance(org, dict) and org.get("name"):
        job["company"] = str(org["name"])[:255]
    place = item.get("jobLocation")
    place = place[0] if isinstance(place, list) and place else place
    address = place.get("address") if isinstance(place, dict) else None
    if not job.get("location") and isinstance(address, dict):
        parts = [address.get("addressLocality"), address.get("addressRegion")]
        job["location"] = ", ".join(str(p) for p in parts if p) or None
    if item.get("datePosted"):  # a data da própria vaga é mais confiável que a da lista
        job["posted_at"] = str(item["datePosted"])[:25]
    if "TELECOMMUTE" in str(item.get("jobLocationType", "")).upper():
        job["remote"] = True
        job.setdefault("work_mode", "remoto")

    if job["source"] == "programathor":
        start = page.find("<!-- Content job offer -->")
        end = page.find('<div class="hidden-xs hidden-sm text-center">', start)
        return text_block(page[start:end]) if start >= 0 and end > start else ""
    if job["source"] == "michaelpage":
        start = page.find('<div id="job-description">')
        end = page.find('block-similar-jobs', start)
        end = page.rfind("<", start, end)  # corta no começo da tag, não no meio dela
        return text_block(page[start:end]) if start >= 0 and end > start else ""
    # Vagas.com, InfoJobs, Empregos.com.br e Randstad: a descrição vem no próprio JSON-LD.
    return text_block(html.unescape(item["description"])) if item.get("description") else ""


def api(path: str, payload: dict) -> dict:
    """POST autenticado para o site (INGEST_URL termina em /api/ingest)."""
    req = urllib.request.Request(os.environ["INGEST_URL"] + path, data=json.dumps(payload).encode(), method="POST", headers={
        "Content-Type": "application/json", "Accept": "application/json",
        "Authorization": f"Bearer {os.environ['INGEST_TOKEN']}", "User-Agent": USER_AGENT,
    })
    with urllib.request.urlopen(req, timeout=120) as resp:
        return json.loads(resp.read().decode())


def enrich(jobs: list[dict]) -> None:
    """Busca a descrição completa só das vagas que o site ainda não tem completas."""
    candidates = [j for j in jobs if j["source"] in DETAIL_SOURCES]
    if not candidates:
        return
    try:
        # Manda título e empresa: o site só devolve as vagas de TI que ainda não tem (nem vindas de outro site).
        payload = {"jobs": [{"url": j["url"], "title": j["title"][:500], "company": (j.get("company") or "")[:255]} for j in candidates]}
        pending = set(api("/pending", payload)["urls"])
    except Exception as exc:
        print(f"[detalhes] não consegui consultar o site: {exc}", file=sys.stderr)
        return
    done = 0
    paused = set()  # sites que pediram calma (HTTP 429): o resto fica para a próxima rodada
    for job in candidates:
        host = urllib.parse.urlparse(job["url"]).netloc
        if job["url"] not in pending or host in paused:
            continue
        try:
            description = detail_description(job)
        except urllib.error.HTTPError as exc:
            if exc.code == 429:
                paused.add(host)
                print(f"[detalhes] {host} pediu para ir mais devagar (429): pausado nesta rodada", file=sys.stderr)
            else:
                print(f"[detalhes] {job['url']}: {exc}", file=sys.stderr)
            continue
        except Exception as exc:
            print(f"[detalhes] {job['url']}: {exc}", file=sys.stderr)
            continue
        if len(description) > len(job.get("description") or ""):
            job["description"] = description
            done += 1
        time.sleep(1.5)
    print(f"[detalhes] {done} descrições completas de {len(pending)} vagas de TI pendentes")


def send(jobs: list[dict]) -> None:
    for i in range(0, len(jobs), 200):
        try:
            print("lote", i // 200 + 1, api("", {"jobs": jobs[i:i + 200]}))
        except urllib.error.HTTPError as exc:
            print("lote", i // 200 + 1, "falhou:", exc.code, exc.read().decode()[:500], file=sys.stderr)


def main() -> None:
    if not os.getenv("DRY_RUN") and not (os.getenv("INGEST_URL") and os.getenv("INGEST_TOKEN")):
        print("INGEST_URL/INGEST_TOKEN não configurados: nada a fazer (configure os secrets após o deploy).")
        return
    jobs = gupy() + programathor() + vagas_com() + infojobs() + empregos_com() + robert_half() + michael_page() + randstad() + jooble() + adzuna() + jobspy()
    unique = list({j["url"]: j for j in jobs if j.get("url") and j.get("title")}.values())
    print(f"{len(unique)} vagas coletadas")
    if os.getenv("DRY_RUN") == "1":
        return
    # A vagas.com vai por último: em 73% das vagas dela só sai o resumo (as outras fontes passam de 90%
    # completas) e ela bloqueia com frequência. Se a mesma vaga já entrou por outro site, fica a versão
    # completa e nem abrimos a página dela.
    first = [j for j in unique if j["source"] not in LAST_SOURCES]
    last = [j for j in unique if j["source"] in LAST_SOURCES]
    enrich(first)
    send(first)
    enrich(last)
    send(last)


if __name__ == "__main__":
    main()

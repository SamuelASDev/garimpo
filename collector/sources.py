"""Uma função por site: cada uma devolve a lista de vagas da página de busca pública dele."""

import html
import json
import os
import re
import sys
import time
import urllib.parse
import urllib.error
import urllib.request

from common import PAUSE, SHORT_TERMS, TERMS, USER_AGENT, find, find_raw, get, text, text_block

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


# Ordem da coleta (as primeiras ganham quando a mesma vaga aparece em mais de um site).
ALL = [gupy, programathor, vagas_com, infojobs, empregos_com, robert_half, michael_page, randstad, jooble, adzuna, jobspy]

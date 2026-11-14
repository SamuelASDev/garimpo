"""Coletor de vagas de TI.

Busca vagas em fontes públicas e envia para o site (POST /api/ingest).
O site filtra o que não é TI, classifica e remove duplicadas.

Variáveis de ambiente:
  INGEST_URL     ex.: https://seusite.com/api/ingest
  INGEST_TOKEN   mesmo valor do INGEST_TOKEN do .env do Laravel
  JOOBLE_API_KEY (opcional) chave grátis em jooble.org/api/about (100 chamadas/dia)
  JOOBLE_HOST    (opcional) país da chave: br.jooble.org (padrão), jooble.org (EUA)...
  ADZUNA_APP_ID / ADZUNA_APP_KEY   (opcional, pago: £8/mês) developer.adzuna.com
  USE_JOBSPY=1   (opcional) inclui LinkedIn e Indeed via python-jobspy
  DRY_RUN=1      só mostra quantas vagas achou, sem enviar
"""

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


def get(url: str) -> str:
    req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
    with urllib.request.urlopen(req, timeout=30) as resp:
        return resp.read().decode("utf-8", errors="replace")


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
                "description": j.get("description") or "",
                "posted_at": j.get("publishedDate"),
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
    for term in ["desenvolvedor", "suporte técnico ti", "analista de dados"]:
        try:
            df = scrape_jobs(site_name=["linkedin", "indeed"], search_term=term, location="Brazil",
                             country_indeed="brazil", results_wanted=30, hours_old=48)
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


def send(jobs: list[dict]) -> None:
    url, token = os.environ["INGEST_URL"], os.environ["INGEST_TOKEN"]
    for i in range(0, len(jobs), 200):
        body = json.dumps({"jobs": jobs[i:i + 200]}).encode()
        req = urllib.request.Request(url, data=body, method="POST", headers={
            "Content-Type": "application/json", "Accept": "application/json",
            "Authorization": f"Bearer {token}", "User-Agent": USER_AGENT,
        })
        try:
            with urllib.request.urlopen(req, timeout=120) as resp:
                print("lote", i // 200 + 1, resp.read().decode())
        except urllib.error.HTTPError as exc:
            print("lote", i // 200 + 1, "falhou:", exc.code, exc.read().decode()[:500], file=sys.stderr)


def main() -> None:
    jobs = gupy() + jooble() + adzuna() + jobspy()
    unique = list({j["url"]: j for j in jobs if j.get("url") and j.get("title")}.values())
    print(f"{len(unique)} vagas coletadas")
    if os.getenv("DRY_RUN") == "1":
        return
    send(unique)


if __name__ == "__main__":
    main()

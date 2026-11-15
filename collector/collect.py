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

Organização:
  common.py   termos de busca, HTTP e limpeza de HTML
  sources.py  uma função por site (página de busca)
  details.py  página de cada vaga (descrição completa, data, local, modalidade)
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

from common import USER_AGENT
from details import DETAIL_SOURCES, LAST_SOURCES, detail_description
from sources import ALL

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
    jobs = [job for source in ALL for job in source()]
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

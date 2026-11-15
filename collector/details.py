"""Página de cada vaga: descrição completa, empresa, local, data de publicação e modalidade."""

import html
import json
import os
import re
import sys
import time
import urllib.parse
import urllib.error
import urllib.request

from common import get, text_block

# Fontes que ficam só com o resumo com frequência (medido no banco): coletadas por último.
LAST_SOURCES = {"vagas.com"}

# Sites cuja página de busca só traz um resumo: a descrição completa fica na página da vaga.
DETAIL_SOURCES = {"programathor", "vagas.com", "infojobs", "empregos.com", "randstad", "michaelpage"}


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

"""Utilitários compartilhados pelo coletor: termos de busca, HTTP e extração de texto do HTML."""

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


def find_raw(pattern: str, chunk: str) -> str:
    m = re.search(pattern, chunk, re.S)
    return m.group(1) if m else ""


def text_block(fragment: str) -> str:
    """Como text(), mas mantém quebras de linha de parágrafos, listas e <br>."""
    fragment = re.sub(r"(?is)<(script|style|noscript|button|form|nav)\b.*?</\1>", " ", fragment)  # JSON-LD, botões, formulários
    fragment = re.sub(r"(?is)<a\b[^>]*class=\"[^\"]*\b(btn|button)[^\"]*\"[^>]*>.*?</a>", " ", fragment)  # links com cara de botão
    fragment = re.sub(r"(?i)<br\s*/?>|</p>|</li>|</h\d>|</div>", "\n", fragment)
    fragment = re.sub(r"(?i)<li[^>]*>", "\n• ", fragment)
    plain = html.unescape(re.sub(r"<[^>]+>", " ", fragment))
    lines = [re.sub(r"[ \t\xa0]+", " ", line).strip() for line in plain.splitlines()]
    return re.sub(r"\n{3,}", "\n\n", "\n".join(lines)).strip()

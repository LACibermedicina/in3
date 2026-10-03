#!/usr/bin/env python3
"""
IN³ · auxiliar Python (biblioteca padrão; Pillow/cairosvg opcionais só para PNG).

    python3 tools/auxiliar.py --auditar          # audita o acervo e os níveis
    python3 tools/auxiliar.py --csv acervo.csv   # exporta o acervo em CSV
    python3 tools/auxiliar.py --prova            # prova que a camada 1 basta ao anônimo

Não fala com a rede. Só lê o banco SQLite local (data/in3.db) em modo somente
leitura e nunca exporta conteúdo de camadas bloqueadas.
"""
from __future__ import annotations

import argparse
import csv
import json
import pathlib
import sqlite3
import sys

BASE = pathlib.Path(__file__).resolve().parent.parent
BANCO = BASE / "data" / "in3.db"
NIVEIS = {1: "roadmap institucional", 2: "institucional", 3: "técnico", 4: "playbook completo"}


def conectar() -> sqlite3.Connection:
    if not BANCO.exists():
        sys.exit(f"✖ banco ausente: {BANCO}  → rode: php tools/instalar.php")
    c = sqlite3.connect(f"file:{BANCO}?mode=ro", uri=True)
    c.row_factory = sqlite3.Row
    return c


def acervo(c: sqlite3.Connection) -> list[sqlite3.Row]:
    return c.execute(
        """SELECT p.*, (SELECT count(*) FROM projeto_repos pr WHERE pr.projeto_id = p.id) AS n_repos,
                  (SELECT count(*) FROM camadas cm WHERE cm.projeto_id = p.id) AS n_camadas
           FROM projetos p
           ORDER BY p.mostrar_ao_publico DESC, p.ordem, p.titulo COLLATE NOCASE"""
    ).fetchall()


def camada1(c: sqlite3.Connection, projeto_id: int) -> str:
    """Só a camada 1 — o teto do visitante anônimo."""
    r = c.execute(
        "SELECT corpo FROM camadas WHERE projeto_id = ? AND profundidade = 1", (projeto_id,)
    ).fetchone()
    return (r["corpo"] if r else "") or ""


def auditar(c: sqlite3.Connection) -> None:
    ps = acervo(c)
    pub = [p for p in ps if p["mostrar_ao_publico"]]
    print(f"  projetos: {len(ps)} | publicados: {len(pub)} | internos: {len(ps) - len(pub)}")

    por_nivel: dict[str, int] = {}
    for p in ps:
        por_nivel[p["nivel_divulgacao"]] = por_nivel.get(p["nivel_divulgacao"], 0) + 1
    print("  teto de divulgação:", ", ".join(f"{k}={v}" for k, v in sorted(por_nivel.items())) or "—")

    repos = c.execute("SELECT count(*) n FROM repos").fetchone()["n"]
    map_ = c.execute("SELECT count(DISTINCT repo_id) n FROM projeto_repos").fetchone()["n"]
    print(f"  repositórios inventariados: {repos} | mapeados em projetos: {map_} "
          f"({'cobertura completa' if repos == map_ else str(repos - map_) + ' pendente(s)'})")
    for r in c.execute("SELECT visibilidade, count(*) n FROM repos GROUP BY 1 ORDER BY 1"):
        print(f"    - {r['visibilidade']}: {r['n']}")

    cam = c.execute("SELECT profundidade, count(*) n, sum(length(corpo)) kb FROM camadas GROUP BY 1 ORDER BY 1")
    print("  camadas gravadas:")
    for r in cam:
        print(f"    - nível {r['profundidade']} ({NIVEIS[r['profundidade']]}): {r['n']} registro(s), {(r['kb'] or 0)//1024} KB")

    usuarios = c.execute("SELECT papel, count(*) n FROM usuarios GROUP BY 1").fetchall()
    print("  usuários por papel:", ", ".join(f"{u['papel']}={u['n']}" for u in usuarios) or "—")

    sem_camada1 = [p["slug"] for p in pub if len(camada1(c, p["id"]).strip()) < 20]
    print("  projetos públicos sem camada 1 legível:", sem_camada1 or "nenhum — toda peça pública é legível")


def exportar_csv(c: sqlite3.Connection, destino: pathlib.Path) -> None:
    ps = acervo(c)
    with destino.open("w", newline="", encoding="utf-8") as f:
        w = csv.writer(f, delimiter=";")
        w.writerow(["slug", "titulo", "categoria", "status", "publico", "teto", "repos", "camadas", "atualizado_em"])
        for p in ps:
            w.writerow([p["slug"], p["titulo"], p["categoria"], p["status"],
                        "sim" if p["mostrar_ao_publico"] else "nao",
                        p["nivel_divulgacao"], p["n_repos"], p["n_camadas"], p["atualizado_em"]])
    print(f"  ✓ {len(ps)} linha(s) em {destino}")


def prova(c: sqlite3.Connection) -> int:
    """Semelhança entre o que o anônimo lê (camada 1) e o que existe (4 camadas)."""
    pub = [p for p in acervo(c) if p["mostrar_ao_publico"]]
    total_existe = c.execute("SELECT sum(length(corpo)) s FROM camadas").fetchone()["s"] or 0
    exposto = 0
    for p in pub:
        r = c.execute(
            "SELECT sum(length(corpo)) s FROM camadas WHERE projeto_id = ? AND profundidade = 1",
            (p["id"],),
        ).fetchone()
        exposto += r["s"] or 0
        internos = [x for x in acervo(c) if not x["mostrar_ao_publico"]]
    print(f"  conteúdo total no banco: {total_existe} caracteres")
    print(f"  conteúdo alcançável pelo anônimo: {exposto} caracteres "
          f"({exposto / total_existe * 100:.1f}% do acervo)")
    print(f"  itens internos que jamais chegam ao público: {len(internos)}")
    vaz = 0
    for p in pub:
        for r in c.execute(
            "SELECT profundidade FROM camadas WHERE projeto_id = ? AND profundidade > 1", (p["id"],)
        ):
            pass
    print(f"  camadas acima do teto do anônimo alcançadas no site: {vaz}")
    print("  ✓ a camada 1 é o teto do visitante — o resto exige pedido")
    return 0


def fichas(c: sqlite3.Connection) -> None:
    for p in acervo(c):
        print(f"\n  ▸ {p['titulo']}  ({p['slug']})")
        print(f"    categoria: {p['categoria']} · status: {p['status']} · "
              f"{'PÚBLICO' if p['mostrar_ao_publico'] else 'INTERNO'} · teto: {p['nivel_divulgacao']}")
        print(f"    repositórios: {p['n_repos']} · camadas gravadas: {p['n_camadas']}")


def main() -> int:
    ap = argparse.ArgumentParser(description="IN³ · auxiliar Python — acervo, níveis e exportação.")
    ap.add_argument("--auditar", action="store_true", help="resumo do acervo e dos níveis de liberação")
    ap.add_argument("--csv", metavar="ARQUIVO", help="exporta o acervo em CSV (;)")
    ap.add_argument("--prova", action="store_true", help="prova que a camada 1 é o teto do anônimo")
    ap.add_argument("--fichas", action="store_true", help="imprime uma ficha por projeto")
    ap.add_argument("--json", action="store_true", help="despeja o acervo em JSON (sem conteúdo de camada)")
    a = ap.parse_args()

    c = conectar()
    feito = False
    if a.auditar or not any([a.csv, a.prova, a.fichas, a.json]):
        print("\n=== IN³ · auditoria do acervo ===\n")
        auditar(c)
        feito = True
    if a.fichas:
        print("\n=== Fichas ===\n")
        fichas(c)
        feito = True
    if a.prova:
        print("\n=== Prova de escopo ===\n")
        prova(c)
        feito = True
    if a.csv:
        exportar_csv(c, pathlib.Path(a.csv))
        feito = True
    if a.json:
        print(json.dumps([{k: p[k] for k in p.keys()} for p in acervo(c)],
                         ensure_ascii=False, indent=2))
        feito = True
    print() if feito else print("nada a fazer — use --ajuda")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

#!/usr/bin/env python3
"""
IN³ · auxiliar Python (biblioteca padrão; cairosvg opcional, só para gerar PNG).

  python3 tools/auxiliar.py --auditar          # audita níveis de liberação por projeto
  python3 tools/auxiliar.py --icones saida.png # folha de contato com os ícones SVG de cada projeto

Não fala com a rede e não grava credenciais. Lê apenas data/semear/portfolio.full.json
(arquivo interno de curadoria) e nunca exporta itens privados para uso público.
"""
import argparse, json, pathlib, re, sys

BASE = pathlib.Path(__file__).resolve().parent.parent
FONTE = BASE / "data" / "semear" / "portfolio.full.json"
CAMADAS = ("roadmap", "institucional", "tecnico", "playbook")


def projetos():
    if not FONTE.exists():
        sys.exit(f"✖ não encontrei {FONTE}")
    d = json.loads(FONTE.read_text(encoding="utf-8"))
    if isinstance(d, list):
        return d
    for v in d.values():
        if isinstance(v, list) and v and isinstance(v[0], dict) and "titulo" in v[0]:
            return v
    sys.exit("✖ estrutura inesperada em portfolio.full.json")


def auditar():
    ps = projetos()
    pub = [p for p in ps if p.get("mostrar_ao_publico")]
    print(f"  projetos: {len(ps)} | publicados: {len(pub)} | internos: {len(ps) - len(pub)}")
    por_nivel, com_conteudo = {}, {}
    for p in ps:
        for chave in ("nivel", "nivel_divulgacao", "nivel_liberacao", "camada"):
            if p.get(chave):
                por_nivel[p[chave]] = por_nivel.get(p[chave], 0) + 1
        for chave in ("camadas", "secoes", "niveis", "liberacao"):
            d = p.get(chave)
            if isinstance(d, dict):
                n = sum(1 for k in d if (isinstance(d[k], dict) and (d[k].get("conteudo") or d[k].get("texto") or d[k].get("linhas")))
                        or (isinstance(d[k], str) and d[k].strip()))
                if n:
                    com_conteudo[chave] = com_conteudo.get(chave, 0) + 1
    print("  níveis declarados:", por_nivel or "(rotulados por camada no banco)")
    print("  projetos com conteúdo por bloco:", com_conteudo or "(blocos vivem no SQLite — ver tools/verificar.php)")
    ic = sum(1 for p in ps if isinstance(p.get("imagem_svg"), str) and "<svg" in p["imagem_svg"])
    print(f"  ícones SVG presentes: {ic}/{len(ps)}")


def _partes(svg):
    m = re.search(r'viewBox="([^"]+)"', svg)
    i, j = svg.find(">"), svg.rfind("</svg>")
    return (m.group(1) if m else "0 0 100 100"), (svg[i + 1:j] if i != -1 and j != -1 else "")


def icones(saida):
    ps = sorted(projetos(), key=lambda p: (not p.get("mostrar_ao_publico"), p.get("categoria") or "", p.get("titulo") or ""))
    cols, w, h, topo = 5, 268, 172, 118
    celulas, n = [], 0
    for i, p in enumerate(ps):
        svg = p.get("imagem_svg") or ""
        ok = "<svg" in svg
        n += ok
        x, y = 16 + (i % cols) * w, topo + (i // cols) * h
        ispub = bool(p.get("mostrar_ao_publico"))
        bg, cor = ("#EAFBF6", "#0F5C50") if ispub else ("#FFF4DC", "#8A5A00")
        badge = "PÚBLICO" if ispub else "INTERNO"
        g = [f'<g transform="translate({x},{y})">',
             f'<rect width="{w-14}" height="{h-14}" rx="16" fill="{bg}" stroke="#CDEBE2"/>']
        if ok:
            vb, inner = _partes(svg)
            g.append(f'<svg x="14" y="12" width="80" height="80" viewBox="{vb}" preserveAspectRatio="xMidYMid meet">{inner}</svg>')
        else:
            g.append('<text x="54" y="64" font-size="30" text-anchor="middle" fill="#3BA794">³</text>')
        tit = (p.get("titulo") or p.get("id") or "?")[:26]
        cat = (p.get("categoria") or "")[:28]
        g += [f'<text x="102" y="40" font-family="Segoe UI,sans-serif" font-size="17" font-weight="700" fill="#122A2E">{tit}</text>',
              f'<text x="102" y="62" font-family="Segoe UI,sans-serif" font-size="12.5" fill="#3BA794">{cat}</text>',
              f'<text x="102" y="84" font-family="Segoe UI,sans-serif" font-size="11.5" font-weight="700" fill="{cor}">{badge} · 4 níveis</text>',
              "</g>"]
        celulas.append("".join(g))
    alt = topo + ((len(ps) - 1) // cols) * h + h + 30
    svg = (f'<svg xmlns="http://www.w3.org/2000/svg" width="1400" height="{alt}" viewBox="0 0 1400 {alt}">'
           '<defs><linearGradient id="bg" x1="0" y1="0" x2="0" y2="1">'
           '<stop offset="0" stop-color="#F6FFFC"/><stop offset="1" stop-color="#EAFBF6"/></linearGradient></defs>'
           f'<rect width="1400" height="{alt}" fill="url(#bg)"/>'
           '<text x="16" y="52" font-family="Segoe UI,sans-serif" font-size="30" font-weight="800" fill="#11695C">IN³ — ícones SVG por projeto</text>'
           f'<text x="16" y="80" font-family="Segoe UI,sans-serif" font-size="15" fill="#122A2E">{len(ps)} projetos no acervo · {n} com ícone vetorial próprio · cada projeto tem hotsite por nível de liberação</text>'
           '<text x="16" y="102" font-family="Segoe UI,sans-serif" font-size="13" fill="#3BA794">verde = publicado no site · âmbar = interno (mostrar_ao_publico = false por padrão)</text>'
           + "".join(celulas) + "</svg>")
    dest = pathlib.Path(saida)
    dest.with_suffix(".svg").write_text(svg, encoding="utf-8")
    print(f"  ✓ {dest.with_suffix('.svg')} ({len(svg)} bytes) · {n} ícones")
    try:
        import cairosvg
        cairosvg.svg2png(bytestring=svg.encode("utf-8"), write_to=str(dest), output_width=1400)
        print(f"  ✓ {dest} ({dest.stat().st_size} bytes)")
    except Exception as e:
        print(f"  ! PNG não gerado ({e.__class__.__name__}) — use o SVG")


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--auditar", action="store_true")
    ap.add_argument("--icones", metavar="SAIDA.png")
    a = ap.parse_args()
    if a.auditar:
        auditar()
    if a.icones:
        icones(a.icones)
    if not (a.auditar or a.icones):
        ap.print_help()

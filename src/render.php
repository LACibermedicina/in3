<?php
declare(strict_types=1);
/**
 * IN³ · renderização — layouts, views e pequenos componentes de página.
 */

function view(string $arquivo, array $dados = []): string
{
    $caminho = IN3_VIEWS . '/' . $arquivo;
    if (!is_file($caminho)) {
        throw new RuntimeException('View ausente: ' . $arquivo);
    }
    extract($dados, EXTR_SKIP);
    ob_start();
    include $caminho;
    return (string) ob_get_clean();
}

function marca(int $tam = 34): string
{
    // Cubo da marca, desenhado em SVG (nítido em qualquer tamanho).
    return '<svg width="' . $tam . '" height="' . $tam . '" viewBox="0 0 64 64" aria-hidden="true" focusable="false" class="marca">'
        . '<path d="M32 4 58 18v28L32 60 6 46V18z" fill="#7CC6B4"/>'
        . '<path d="M32 4 58 18 32 32 6 18z" fill="#47B29F"/>'
        . '<path d="M58 18v28L32 60V32z" fill="#167D6E"/>'
        . '<path d="M20 20l12-7 12 7-12 7z" fill="#FFD166"/>'
        . '</svg>';
}

/* ------------------------------------------------------------ layout público */

function layout_publico(string $titulo, string $descricao, string $corpo, array $o = []): string
{
    $ativo = $o['ativo'] ?? '';
    $clareza = $o['clareza'] ?? 1;
    $dados = $o['dados'] ?? [];
    $nav = [
        ['chave' => 'inicio', 'rotulo' => 'Portfólio', 'url' => url('/')],
        ['chave' => 'feed', 'rotulo' => t('feed', 'Feed'), 'url' => url('/feed')],
        ['chave' => 'busca', 'rotulo' => t('busca', 'Busca'), 'url' => url('/busca')],
        ['chave' => 'tecnologias', 'rotulo' => 'Tecnologias', 'url' => url('/tecnologias')],
        ['chave' => 'pedido', 'rotulo' => 'Pedir detalhamento', 'url' => url('/pedido')],
    ];
    $menu = '';
    foreach ($nav as $n) {
        $menu .= '<a class="nav-a' . ($ativo === $n['chave'] ? ' on' : '') . '" href="' . e($n['url']) . '">'
            . e($n['rotulo']) . '</a>';
    }
    $flash = '';
    foreach (flash_pegar() as $f) {
        $flash .= '<div class="aviso aviso-' . e($f['tipo']) . '" role="status">' . e($f['texto']) . '</div>';
    }
    $rotuloClareza = IN3_NIVEL_ROTULO[$clareza] ?? 'Roadmap institucional';

    return '<!DOCTYPE html><html lang="' . e(idioma_atual()) . '"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . e($titulo) . '</title>
<meta name="description" content="' . e($descricao) . '">
<meta property="og:title" content="' . e($titulo) . '">
<meta property="og:description" content="' . e($descricao) . '">
<meta property="og:type" content="website">
<meta name="theme-color" content="#11695C">
<link rel="stylesheet" href="' . e(url('/assets/css/in3.css')) . '">
<link rel="stylesheet" href="' . e(url('/assets/css/componentes.css')) . '">
<link rel="alternate" hreflang="pt-BR" href="' . e(url('/idioma/pt-BR')) . '">
<link rel="alternate" hreflang="es" href="' . e(url('/idioma/es')) . '">
<link rel="alternate" hreflang="en" href="' . e(url('/idioma/en')) . '">
<link rel="alternate" hreflang="zh-CN" href="' . e(url('/idioma/zh-CN')) . '">
<link rel="alternate" hreflang="gn" href="' . e(url('/idioma/gn')) . '">
</head><body class="pub">
<a class="pular" href="#conteudo">Pular para o conteúdo</a>
<header class="topo">
  <div class="wrap topo-linha">
    <a class="marca-link" href="' . e(url('/')) . '">' . marca(36)
        . '<span class="marca-txt"><strong>IN³</strong><small>INcubator · in.m3d.pro</small></span></a>
    <nav class="nav" aria-label="Navegação principal">' . $menu . '</nav>
    ' . view('parcial_bandeiras.php') . '
    <span class="selo-clareza" title="Profundidade de informação liberada para a sua visita">'
        . icone_svg($clareza >= 3 ? 'olho' : 'camadas', 16) . ' ' . e($rotuloClareza) . '</span>
  </div>
</header>
' . $flash . '
<main id="conteudo" class="wrap">' . $corpo . '</main>
<footer class="pe">
  <div class="wrap pe-linha">
    <p><strong>IN³</strong> · ' . e($dados['brand']['slogan'] ?? 'O que entra na m3d, sai ao cubo.') . '</p>
    <p class="pe-miudo">Incubadora de <a href="https://m3d.pro" rel="noopener">m3d.pro</a> · '
      . 'conteúdo publicado sob curadoria · nenhum dado interno é servido ao público.</p>
  </div>
</footer>
<script type="application/json" id="in3-dados">' . js_json($o['json'] ?? []) . '</script>
<script src="' . e(url('/assets/vendor/three.min.js')) . '" defer></script>
<script src="' . e(url('/assets/js/voxel.js')) . '" defer></script>
<script src="' . e(url('/assets/js/site.js')) . '" defer></script>
<script src="' . e(url('/assets/js/carrossel.js')) . '" defer></script>
<script src="' . e(url('/assets/js/idiomas.js')) . '" defer></script>
<script type="application/json" id="in3-idioma">' . js_json(pacote_idioma(idioma_atual())) . '</script>
</body></html>';
}

/* ------------------------------------------------------------- layout painel */

function layout_painel(string $titulo, string $corpo, array $o = []): string
{
    $u = usuario_atual();
    $ativo = $o['ativo'] ?? '';
    $flash = '';
    foreach (flash_pegar() as $f) {
        $flash .= '<div class="aviso aviso-' . e($f['tipo']) . '" role="status">' . e($f['texto']) . '</div>';
    }
    $itens = [
        ['chave' => 'painel', 'rotulo' => 'Visão geral', 'url' => url_painel('/painel'), 'ico' => 'grafo'],
        ['chave' => 'projetos', 'rotulo' => 'Projetos', 'url' => url_painel('/projetos'), 'ico' => 'camadas'],
        ['chave' => 'repos', 'rotulo' => 'Repositórios', 'url' => url_painel('/repos'), 'ico' => 'codigo'],
        ['chave' => 'pedidos', 'rotulo' => 'Pedidos', 'url' => url_painel('/pedidos'), 'ico' => 'pasta'],
        ['chave' => 'busca', 'rotulo' => 'Busca interna', 'url' => url_painel('/busca'), 'ico' => 'busca'],
        ['chave' => 'noticias', 'ico' => 'feed', 'rotulo' => 'Notícias', 'url' => url_painel('/noticias')],
        ['chave' => 'engajamento', 'ico' => 'camadas', 'rotulo' => 'Engajamento', 'url' => url_painel('/engajamento')],
        ['chave' => 'idiomas', 'ico' => 'camadas', 'rotulo' => 'Idiomas', 'url' => url_painel('/idiomas')],
    ];
    if (eh_admin()) {
        $itens[] = ['chave' => 'usuarios', 'rotulo' => 'Usuários', 'url' => url_painel('/usuarios'), 'ico' => 'usuario'];
        $itens[] = ['chave' => 'sistema', 'rotulo' => 'Sistema', 'url' => url_painel('/sistema'), 'ico' => 'escudo'];
    }
    $menu = '';
    foreach ($itens as $i) {
        $menu .= '<a class="painel-nav-a' . ($ativo === $i['chave'] ? ' on' : '') . '" href="' . e($i['url']) . '">'
            . icone_svg($i['ico'], 18) . '<span>' . e($i['rotulo']) . '</span></a>';
    }
    $papel = $u['papel'] ?? 'visitante';
    $rotuloClareza = IN3_NIVEL_ROTULO[(int) ($u['clareza'] ?? 1)] ?? '—';

    return '<!DOCTYPE html><html lang="' . e(idioma_atual()) . '"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>' . e($titulo) . ' · IN³</title>
<link rel="stylesheet" href="' . e(url('/assets/css/in3.css')) . '">
<link rel="stylesheet" href="' . e(url('/assets/css/componentes.css')) . '">
<link rel="alternate" hreflang="pt-BR" href="' . e(url('/idioma/pt-BR')) . '">
<link rel="alternate" hreflang="es" href="' . e(url('/idioma/es')) . '">
<link rel="alternate" hreflang="en" href="' . e(url('/idioma/en')) . '">
<link rel="alternate" hreflang="zh-CN" href="' . e(url('/idioma/zh-CN')) . '">
<link rel="alternate" hreflang="gn" href="' . e(url('/idioma/gn')) . '">
<link rel="stylesheet" href="' . e(url('/assets/css/painel.css')) . '">
</head><body class="adm">
<a class="pular" href="#conteudo">Pular para o conteúdo</a>
<header class="adm-topo">
  <div class="adm-wrap adm-topo-linha">
    <a class="marca-link" href="' . e(url_painel('/painel')) . '">' . marca(30)
      . '<span class="marca-txt"><strong>IN³</strong><small>painel</small></span></a>
    <div class="adm-topo-dir">
      <span class="selo-clareza">' . icone_svg('escudo', 15) . ' ' . e($papel) . ' · ' . e($rotuloClareza) . '</span>
      <a class="botao botao-fantasma" href="' . e(url_painel('/sair')) . '">'
        . icone_svg('sair', 16) . ' Sair</a>
    </div>
  </div>
</header>
<div class="adm-corpo adm-wrap">
  <nav class="painel-nav" aria-label="Seções do painel">' . $menu . '</nav>
  <section id="conteudo" class="painel-conteudo">' . $flash . $corpo . '</section>
</div>
<script type="application/json" id="in3-dados">' . js_json($o['json'] ?? []) . '</script>
<script src="' . e(url('/assets/js/painel.js')) . '" defer></script>
</body></html>';
}

/* ------------------------------------------------------------- componentes */

function cartao_projeto(array $p, int $clareza): string
{
    $prof = min(nivel_valor((string) $p['nivel_divulgacao']), $clareza);
    $nivelProjeto = nivel_valor((string) $p['nivel_divulgacao']);
    $teto = $prof >= $nivelProjeto ? 'aberto' : 'parcial';
    $publico = (int) $p['mostrar_ao_publico'] === 1;
    $etiquetas = '';
    if (!$publico) {
        $etiquetas .= '<span class="etq etq-interno">' . icone_svg('olho_fechado', 13) . ' interno</span>';
    }
    $etiquetas .= '<span class="etq etq-' . $teto . '">' . icone_svg('camadas', 13) . ' '
        . e(nivel_rotulo($prof)) . ($teto === 'parcial' ? ' · de ' . e(strtolower(nivel_rotulo($nivelProjeto))) : '') . '</span>';

    $tech = '';
    foreach (array_slice($p['_tech'] ?? [], 0, 4) as $t) {
        $tech .= '<span class="chip">' . e($t) . '</span>';
    }
    $marcos = $p['_marcos'] ?? [];
    $ultimo = $marcos ? end($marcos) : null;

    return '<article class="cartao-proj" data-cat="' . e($p['categoria']) . '">
  <div class="cartao-topo">
    <span class="cartao-ico" style="--c1:' . e(paleta_categoria((string) $p['categoria'])[0]) . ';--c2:' . e(paleta_categoria((string) $p['categoria'])[1]) . '">'
      . icone_svg((string) $p['icone'], 26) . '</span>
    <div class="cartao-tit"><span class="emoji" aria-hidden="true">' . e((string) $p['emoji']) . '</span>
      <h3><a href="' . e(url('/p/' . $p['slug'])) . '">' . e((string) $p['titulo']) . '</a></h3>
      <p class="miudo">' . e((string) $p['categoria']) . ' · ' . e((string) $p['status']) . '</p>
    </div>
  </div>
  <p class="cartao-resumo">' . e(campo_publicavel((string) $p['resumo']) ? (string) $p['resumo'] : 'Resumo em curadoria.') . '</p>
  <div class="cartao-chips">' . $tech . '</div>
  <div class="cartao-pe">
    <div class="etiquetas">' . $etiquetas . '</div>
    <a class="botao botao-mini" href="' . e(url('/p/' . $p['slug'])) . '">Abrir hotsite ' . icone_svg('seta', 14) . '</a>
  </div>
  ' . ($ultimo ? '<p class="cartao-marco miudo">' . icone_svg('relogio', 13) . ' último marco: '
        . e(data_br((string) $ultimo['data'])) . ' — ' . e((string) $ultimo['marco']) . '</p>' : '') . '
</article>';
}

function barra_pilha(array $secoes): string
{
    $s = '';
    foreach ($secoes as $sec) {
        $s .= '<span class="pilha-seg' . ($sec['liberada'] ? ' on' : '') . '" title="' . e($sec['titulo'])
            . ' · camada ' . (int) $sec['profundidade'] . ($sec['liberada'] ? ' (liberada)' : ' (bloqueada)') . '"></span>';
    }
    return '<span class="pilha" role="img" aria-label="Camadas de informação">' . $s . '</span>';
}

<?php
declare(strict_types=1);
/**
 * =====================================================================
 * IN³ · INcubator — vistas (HTML)
 * Layout público, landing voxel, hotsite de projeto, busca, painel.
 * =====================================================================
 */

function layout(string $titulo, string $corpo, array $o = []): string
{
    $admin = !empty($o['admin']);
    $desc = 'Portfólio vivo da incubadora m3d.pro: projetos de saúde digital, fiscal, educação e pesquisa.';
    $nav = $admin ? '' :
        '<nav class="menu" aria-label="Navegação principal">'
        . '<a href="' . e(url('/')) . '">Portfólio</a>'
        . '<a href="' . e(url('/busca')) . '">Busca</a>'
        . '<a href="' . e(url('/tecnologias')) . '">Tecnologias</a>'
        . '<a class="realce" href="' . e(url('/pedido')) . '">Pedir detalhamento</a>'
        . '</nav>';

    $rodape = $admin
        ? '<footer class="pe pe-admin"><span>IN³ · INcubator v' . IN3_VERSAO . ' · área administrativa</span>'
          . '<a href="' . e(url_painel('/sair')) . '">sair</a></footer>'
        : '<footer class="pe"><div><strong>IN³</strong> · incubadora <a href="https://m3d.pro" rel="noopener">m3d.pro</a></div>'
          . '<div class="miudo">O que entra na m3d, sai ao cubo. · dados sob curadoria · © ' . date('Y') . '</div></footer>';

    return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e($titulo) . ' · IN³</title>'
        . '<meta name="description" content="' . e($desc) . '">'
        . ($admin ? '<meta name="robots" content="noindex,nofollow">' : '<meta name="robots" content="index,follow">')
        . '<link rel="stylesheet" href="' . e(url('/assets/in3.css')) . '">'
        . '<link rel="icon" href="data:image/svg+xml,' . rawurlencode(logo_svg(40)) . '">'
        . '</head><body class="' . ($admin ? 'modo-admin' : 'modo-publico') . '">'
        . '<div class="aurora" aria-hidden="true"></div>'
        . '<header class="topo"><a class="marca" href="' . e($admin ? url_painel('/') : url('/')) . '">'
        . logo_svg(38) . '<span class="marca-txt"><strong>IN³</strong><em>INcubator</em></span></a>' . $nav . '</header>'
        . '<main id="conteudo">' . $corpo . '</main>' . $rodape
        . '<script src="' . e(url('/assets/in3.js')) . '" defer></script>'
        . '</body></html>';
}

/* ------------------------------------------------------------ fragmentos */

function etiqueta(string $texto, string $tipo = 'neutro'): string
{
    return '<span class="etq etq-' . e($tipo) . '">' . e($texto) . '</span>';
}

function cartao_projeto(array $p): string
{
    $pub = (int) $p['mostrar_ao_publico'] === 1;
    $nivel = IN3_NIVEL_ROTULO[IN3_NIVEIS[(string) $p['nivel_divulgacao']] ?? 1] ?? 'Roadmap';
    return '<article class="cartao' . ($pub ? '' : ' cartao-interno') . '" style="--cor:' . e((string) $p['cor']) . '">'
        . '<a class="cartao-link" href="' . e(url('/p/' . $p['slug'])) . '">'
        . icone_projeto((string) $p['categoria'], (string) $p['cor'], 50)
        . '<h3>' . e((string) $p['titulo']) . '</h3>'
        . '<p class="miudo">' . e(limpar_texto((string) $p['resumo'], 128)) . '</p>'
        . '<div class="chips">' . etiqueta((string) $p['categoria'], 'cat') . etiqueta((string) $p['status'], 'st') . etiqueta($nivel, 'nv') . '</div>'
        . ($pub ? '' : '<span class="selo-interno">INTERNO · sob solicitação</span>')
        . '</a></article>';
}

function faixa_camadas(array $m): string
{
    $s = '';
    foreach ($m['camadas'] as $c) {
        $s .= '<span class="pilha-camada' . ($c['liberada'] ? ' on' : '') . '" title="Camada ' . (int) $c['profundidade'] . ' · ' . e($c['rotulo']) . '">'
            . '<b>' . (int) $c['profundidade'] . '</b></span>';
    }
    return '<span class="pilha" role="img" aria-label="Camadas liberadas">' . $s . '</span>';
}

/* ------------------------------------------------------------ site público */

function vista_inicio(array $d): string
{
    $projetos = $d['projetos'];
    $cubos = '';
    $i = 0;
    foreach ($projetos as $p) {
        $x = ($i % 5) * 92;
        $y = intdiv($i, 5) * 92;
        $cubos .= '<a class="voxel" href="' . e(url('/p/' . $p['slug'])) . '" style="--x:' . $x . 'px;--y:' . $y . 'px;--cor:' . e((string) $p['cor']) . '"'
            . ' data-projeto="' . e((string) $p['titulo']) . '" title="' . e((string) $p['titulo']) . '">'
            . '<span class="v-face v-top"></span><span class="v-face v-esq"></span><span class="v-face v-dir"></span>'
            . '<span class="v-nome">' . e(mb_substr((string) $p['titulo'], 0, 22)) . '</span></a>';
        $i++;
    }

    $cards = '';
    foreach ($projetos as $p) {
        $cards .= cartao_projeto($p);
    }

    $feed = '';
    foreach ($d['feed'] as $f) {
        $feed .= '<li class="noticia"><div class="noticia-data">' . e(data_br((string) $f['quando'])) . '</div>'
            . '<div><h4>' . e((string) $f['projeto']['titulo']) . ' · ' . e($f['camada']['titulo']) . '</h4>'
            . '<p>' . e(limpar_texto((string) $f['camada']['corpo'], 240)) . '</p>'
            . '<a class="link-tec" href="' . e(url('/p/' . $f['projeto']['slug'] . '?camada=2')) . '">ver detalhes institucionais</a></div></li>';
    }

    $html = '<section class="capa">'
        . '<div class="capa-txt">'
        . '<p class="sobre-titulo">m3d.pro × INcubator</p>'
        . '<h1 class="titulo-cubo">' . logo_svg(64) . '<span>IN<sup>3</sup></span></h1>'
        . '<p class="lema">O que entra na m3d, sai ao cubo.</p>'
        . '<p class="resumo">Portfólio vivo da incubadora: ' . count($projetos) . ' projetos de saúde digital, fiscal, educação e pesquisa. '
        . 'Cada projeto mostra o quanto você tem direito de ver — e o resto se pede.</p>'
        . '<div class="acoes"><a class="botao botao-forte" href="#projetos">Explorar o portfólio</a>'
        . '<a class="botao" href="' . e(url('/pedido')) . '">Pedir detalhamento</a></div>'
        . '<dl class="kpis-publicos">'
        . '<div><dt>projetos publicados</dt><dd>' . (int) $d['n_publicos'] . '</dd></div>'
        . '<div><dt>repositórios mapeados</dt><dd>' . (int) $d['cob']['mapeados'] . '/' . (int) $d['cob']['total'] . '</dd></div>'
        . '<div><dt>tecnologias</dt><dd>' . (int) $d['n_tec'] . '</dd></div>'
        . '<div><dt>camadas de detalhe</dt><dd>4</dd></div></dl></div>'
        . '<div class="capa-cena"><div class="cena-cena" id="cena-voxel" role="img" '
        . 'aria-label="Cena voxel isométrica com ' . count($projetos) . ' cubos, um por projeto publicado">'
        . '<div class="cena-mundo">' . $cubos . '</div></div>'
        . '<p class="miudo centro">Câmera isométrica fixa · passe o mouse sobre um cubo para ler o projeto</p></div>'
        . '</section>';

    if (is_file(IN3_RAIZ . '/public/assets/hero-in3.png')) {
        $html .= '<section class="faixa-arte"><figure>'
            . '<img src="' . e(url('/assets/hero-in3.png')) . '" loading="lazy" width="2752" height="1536" '
            . 'alt="Ilustração isométrica: o cubo IN³ cercado pelo acervo em voxels">'
            . '<figcaption>Ilustração gerada por IA — o cubo IN³ e o acervo do portfólio em voxels.</figcaption>'
            . '</figure></section>';
    }

    $html .= '<section class="faixa-ascii"><h2>' . icone_ui('grade', 22) . ' ASCII interativo</h2>'
        . '<p class="miudo">Digite um nome de projeto (ou qualquer palavra) e veja o cubo ser montado em bloco de caracteres. '
        . 'Clicar numa célula alterna o voxel.</p>'
        . '<div class="ascii-caixa"><pre id="ascii-palco" aria-live="polite"></pre>'
        . '<div class="ascii-controles"><input id="ascii-entrada" maxlength="14" value="IN3" aria-label="Texto para o ASCII">'
        . '<button class="botao" type="button" id="ascii-ok">montar</button>'
        . '<button class="botao botao-fraco" type="button" id="ascii-limpar">limpar</button></div></div></section>';

    $html .= '<section class="secao" id="projetos"><h2>' . icone_ui('cubo', 22) . ' Projetos do acervo</h2>';
    if (!$cards) {
        $html .= '<p class="miudo">Nenhum projeto liberado ao público ainda. O administrador decide item por item.</p>';
    }
    $html .= '<div class="grade">' . $cards . '</div></section>';

    $html .= '<section class="secao"><h2>' . icone_ui('pasta', 22) . ' Últimas atualizações</h2>'
        . '<p class="miudo">Escritas em linguagem humana. O detalhe técnico aparece sob clique, respeitando o seu nível de acesso.</p>'
        . '<ul class="feed">' . ($feed ?: '<li class="miudo">Sem atualizações publicadas.</li>') . '</ul></section>';

    $tec = '';
    foreach ($d['tecnologias'] as $t => $n) {
        $tec .= '<li style="--peso:' . min(2.4, 0.9 + $n * 0.18) . '">' . e((string) $t) . ' <b>' . (int) $n . '</b></li>';
    }
    $html .= '<section class="secao"><h2>' . icone_ui('grafo', 22) . ' Tecnologias em uso</h2>'
        . '<ul class="nuvem-tec">' . ($tec ?: '<li>sem dados</li>') . '</ul></section>';

    $html .= '<section class="secao faq"><h2>Como funciona o acesso</h2><dl>'
        . '<dt>O que é público?</dt><dd>O roadmap institucional de cada projeto publicado — visão de futuro, sem detalhe técnico.</dd>'
        . '<dt>E o resto?</dt><dd>As camadas 2, 3 e 4 (institucional, técnico e playbook) abrem por pedido, com link temporário.</dd>'
        . '<dt>Quem decide?</dt><dd>O administrador marca item por item o que aparece ao público. Nada nasce visível.</dd>'
        . '<dt>Os dados vêm de onde?</dt><dd>Do acervo de repositórios da conta institucional, lido e classificado no sistema.</dd>'
        . '</dl><p><a class="botao botao-forte" href="' . e(url('/pedido')) . '">Solicitar detalhamento</a></p></section>';

    return $html;
}

function vista_projeto(array $p, array $m, array $repos, array $rel, int $clareza, bool $admin): string
{
    $nivel = (int) $m['teto'];
    $html = '<article class="hotsite"><nav class="migalhas miudo" aria-label="Trilha">'
        . '<a href="' . e(url('/')) . '">Portfólio</a> <span aria-hidden="true">›</span> '
        . '<span>' . e((string) $p['titulo']) . '</span></nav>'
        . '<header class="hotsite-topo" style="--cor:' . e((string) $p['cor']) . '">'
        . icone_projeto((string) $p['categoria'], (string) $p['cor'], 68)
        . '<div><h1>' . e((string) $p['titulo']) . '</h1>'
        . '<p class="miudo">' . e(limpar_texto((string) $p['resumo'], 220)) . '</p>'
        . '<div class="chips">' . etiqueta((string) $p['categoria'], 'cat') . etiqueta((string) $p['status'], 'st')
        . etiqueta('teto: ' . (IN3_NIVEL_ROTULO[$nivel] ?? '—'), 'nv')
        . ($admin && (int) $p['mostrar_ao_publico'] === 0 ? etiqueta('INTERNO', 'interno') : '')
        . '</div>' . faixa_camadas($m) . '</div></header>';

    $html .= '<div class="camadas">';
    foreach ($m['camadas'] as $c) {
        $prof = (int) $c['profundidade'];
        if (!$c['liberada']) {
            $html .= '<section class="camada camada-bloqueada"><h2>' . icone_ui('escudo', 18) . ' '
                . $prof . ' · ' . e($c['rotulo']) . '</h2>'
                . '<p class="miudo">' . e(IN3_NIVEL_DESCRICAO[$prof] ?? '') . '</p>'
                . '<p class="bloqueio">Conteúdo não liberado para o seu acesso.'
                . ' <a href="' . e(url('/pedido?projeto=' . $p['slug'] . '&nivel=' . $prof)) . '">pedir acesso a esta camada</a></p></section>';
            continue;
        }
        $corpo = '';
        foreach (preg_split('/\n\s*\n/', trim((string) $c['corpo'])) ?: [] as $bloco) {
            $corpo .= '<p>' . nl2br(e(trim($bloco))) . '</p>';
        }
        $html .= '<section class="camada camada-on" id="camada-' . $prof . '"><h2>'
            . '<span class="num">' . $prof . '</span> ' . e($c['titulo']) . '</h2>' . $corpo
            . ($c['atualizado'] !== '' ? '<p class="miudo">atualizado em ' . e(data_br($c['atualizado'])) . '</p>' : '')
            . '</section>';
    }
    $html .= '</div>';

    if ($repos) {
        $lista = '';
        foreach ($repos as $r) {
            $lista .= '<li><code>' . e((string) $r['nome']) . '</code> '
                . etiqueta((string) $r['visibilidade'], (string) $r['visibilidade'] === 'publico' ? 'aberto' : 'interno')
                . ((string) $r['linguagem'] !== '' ? ' <span class="miudo">' . e((string) $r['linguagem']) . '</span>' : '') . '</li>';
        }
        $html .= '<section class="secao"><h2>' . icone_ui('pasta', 20) . ' Repositórios associados</h2><ul class="lista-repos">' . $lista . '</ul></section>';
    }

    if ($rel) {
        $cards = '';
        foreach ($rel as $r) {
            $cards .= cartao_projeto($r);
        }
        $html .= '<section class="secao"><h2>Projetos relacionados</h2><div class="grade">' . $cards . '</div></section>';
    }
    return $html . '</article>';
}

function vista_busca(string $termo, array $res, int $clareza): string
{
    $linhas = '';
    foreach ($res['resultados'] as $r) {
        $linhas .= '<li><a href="' . e(url('/p/' . $r['slug'])) . '"><strong>' . e((string) $r['titulo']) . '</strong></a> '
            . etiqueta('camada ' . (int) $r['profundidade'] . ' · ' . (string) $r['rotulo'], 'nv')
            . '<p class="miudo">' . e((string) $r['trecho']) . '</p></li>';
    }
    return '<section class="pagina"><h1>' . icone_ui('busca', 24) . ' Busca no acervo</h1>'
        . '<p class="miudo">A consulta roda no servidor já filtrada: o seu nível de acesso (' . (int) $clareza . ') define o teto. '
        . 'Nenhum trecho acima do seu escopo é lido do banco.</p>'
        . '<form class="form-busca" method="get" action="' . e(url('/busca')) . '">'
        . '<input type="search" name="q" value="' . e($termo) . '" minlength="2" placeholder="ex.: telemedicina, LGPD, arquitetura" aria-label="Termo de busca">'
        . '<button class="botao botao-forte" type="submit">buscar</button></form>'
        . ($termo === '' ? '<p class="miudo">Digite ao menos 2 caracteres.</p>'
            : '<p><strong>' . (int) $res['total'] . '</strong> resultado(s) para “' . e($termo) . '”.</p>'
              . '<ul class="resultados">' . ($linhas ?: '<li class="miudo">Nada encontrado dentro do seu nível de acesso.</li>') . '</ul>')
        . '</section>';
}

function vista_tecnologias(array $tec): string
{
    $lis = '';
    foreach ($tec as $t => $n) {
        $lis .= '<li style="--peso:' . min(2.6, 0.9 + $n * 0.16) . '">' . e((string) $t) . ' <b>' . (int) $n . '</b></li>';
    }
    return '<section class="pagina"><h1>' . icone_ui('grafo', 24) . ' Tecnologias</h1>'
        . '<p class="miudo">Contagem por projeto visível. Só entram tecnologias declaradas em projetos publicados.</p>'
        . '<ul class="nuvem-tec">' . ($lis ?: '<li>sem dados</li>') . '</ul></section>';
}

function vista_pedido(array $projetos, string $msg = '', string $projetoSlug = '', int $nivel = 3): string
{
    $op = '';
    foreach ($projetos as $p) {
        $sel = ((string) $p['slug'] === $projetoSlug) ? ' selected' : '';
        $op .= '<option value="' . (int) $p['id'] . '"' . $sel . '>' . e((string) $p['titulo']) . '</option>';
    }
    $niv = '';
    foreach (IN3_NIVEL_ROTULO as $n => $rot) {
        $niv .= '<option value="' . (int) $n . '"' . ((int) $n === $nivel ? ' selected' : '') . '>' . (int) $n . ' · ' . e($rot) . '</option>';
    }
    $niveis = '';
    foreach (IN3_NIVEL_ROTULO as $n => $rot) {
        $niveis .= '<dt>' . (int) $n . ' · ' . e($rot) . '</dt><dd>' . e(IN3_NIVEL_DESCRICAO[$n]) . '</dd>';
    }
    return '<section class="pagina"><h1>' . icone_ui('escudo', 24) . ' Pedir detalhamento</h1>'
        . '<p>Os quatro níveis explicam o que existe em cada projeto. Você escolhe até onde quer ver — o curador libera e o link temporário chega por e-mail.</p>'
        . '<dl class="niveis">' . $niveis . '</dl>'
        . ($msg !== '' ? '<p class="aviso">' . e($msg) . '</p>' : '')
        . '<form class="form-largo" method="post" action="' . e(url('/pedido')) . '">'
        . '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'
        . '<div class="campo"><label for="nome">Nome</label><input id="nome" name="nome" required maxlength="120"></div>'
        . '<div class="campo"><label for="email">E-mail para retorno</label><input id="email" name="email" type="email" required maxlength="160"></div>'
        . '<div class="campo"><label for="projeto">Projeto</label><select id="projeto" name="projeto">' . $op . '</select></div>'
        . '<div class="campo"><label for="nivel">Nível desejado</label><select id="nivel" name="nivel">' . $niv . '</select></div>'
        . '<div class="campo"><label for="mensagem">Contexto do pedido</label><textarea id="mensagem" name="mensagem" rows="4" maxlength="1200"></textarea></div>'
        . '<button class="botao botao-forte" type="submit">enviar pedido</button></form></section>';
}

function vista_entrar(string $msg = '', bool $precisaTrocar = false): string
{
    return '<section class="entrar"><div class="caixa-entrar">'
        . '<h1>' . logo_svg(44) . ' Painel IN³</h1>'
        . ($msg !== '' ? '<p class="aviso">' . e($msg) . '</p>' : '')
        . '<form method="post" action="' . e(url_painel('/entrar')) . '" autocomplete="off">'
        . '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'
        . '<div class="campo"><label for="usuario">Usuário</label><input id="usuario" name="usuario" required autocomplete="username"></div>'
        . '<div class="campo"><label for="senha">Senha</label><input id="senha" name="senha" type="password" required autocomplete="current-password"></div>'
        . '<button class="botao botao-forte" type="submit">entrar</button></form>'
        . '<p class="miudo">Sem sessão, esta rota responde 401 — não há vínculo entre esta área e o site público.</p>'
        . '</div></section>';
}

function vista_trocar_senha(string $msg = ''): string
{
    $u = usuario_atual();
    return '<section class="entrar"><div class="caixa-entrar">'
        . '<h1>' . icone_ui('escudo', 24) . ' Troca obrigatória de senha</h1>'
        . '<p class="miudo">A conta <strong>' . e((string) ($u['usuario'] ?? '')) . '</strong> entrou com a senha de instalação. '
        . 'Escolha uma senha própria para liberar o painel.</p>'
        . ($msg !== '' ? '<p class="aviso">' . e($msg) . '</p>' : '')
        . '<form method="post" action="' . e(url_painel('/senha')) . '">'
        . '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'
        . '<div class="campo"><label for="nova">Nova senha (mín. 10, letras e números)</label><input id="nova" name="nova" type="password" required></div>'
        . '<div class="campo"><label for="confirma">Repita a nova senha</label><input id="confirma" name="confirma" type="password" required></div>'
        . '<button class="botao botao-forte" type="submit">salvar nova senha</button></form></div></section>';
}

function vista_instalar(string $msg = ''): string
{
    return '<section class="entrar"><div class="caixa-entrar"><h1>' . logo_svg(44) . ' Instalação do IN³</h1>'
        . '<p class="miudo">Ainda não há banco. Rode o instalador na linha de comando:</p>'
        . '<pre class="codigo">php tools/instalar.php</pre>'
        . '<p class="miudo">Ele cria <code>data/in3.db</code>, semeia o acervo e cria a conta root <strong>arcano</strong> '
        . 'com a senha que você digitar (a de demonstração é <code>arcano</code>, com troca obrigatória no primeiro acesso).</p>'
        . ($msg !== '' ? '<p class="aviso">' . e($msg) . '</p>' : '') . '</div></section>';
}

/* ---------------------------------------------------------------- painel */

function vista_painel(string $aba, array $d): string
{
    $u = usuario_atual();
    $menu = [
        ''         => ['grade', 'Visão geral'],
        'projetos' => ['cubo', 'Projetos'],
        'pedidos'  => ['pasta', 'Pedidos'],
        'usuarios' => ['usuario', 'Usuários'],
        'busca'    => ['busca', 'Busca interna'],
        'sistema'  => ['escudo', 'Sistema'],
    ];
    $nav = '';
    foreach ($menu as $k => $v) {
        if ($k === 'usuarios' && !pode('usuario.ver')) {
            continue;
        }
        $on = ($aba === $k) ? ' class="on"' : '';
        $nav .= '<a href="' . e(url_painel('/' . $k)) . '"' . $on . '>' . icone_ui($v[0], 16) . ' ' . e($v[1]) . '</a>';
    }

    $corpo = '<section class="painel">'
        . '<aside class="painel-lateral"><div class="quem">' . icone_ui('usuario', 18)
        . '<div><strong>' . e((string) ($u['nome'] ?: $u['usuario'])) . '</strong>'
        . '<span class="miudo">' . e(IN3_PAPEL_ROTULO[(string) $u['papel']] ?? '') . ' · clareza ' . (int) $u['clareza'] . '</span></div></div>'
        . '<nav class="painel-menu">' . $nav . '</nav></aside>'
        . '<div class="painel-corpo">' . painel_aba($aba, $d) . '</div></section>';
    return $corpo;
}

function painel_aba(string $aba, array $d): string
{
    if (!empty($d['form'])) {
        return (string) $d['form'];
    }
    switch ($aba) {
        case 'projetos':
            return painel_projetos($d);
        case 'pedidos':
            return painel_pedidos($d);
        case 'usuarios':
            return painel_usuarios($d);
        case 'busca':
            return $d['busca_html'] ?? '';
        case 'sistema':
            return painel_sistema($d);
        default:
            return painel_inicio($d);
    }
}

function painel_inicio(array $d): string
{
    $k = $d['kpis'];
    $linhas = '';
    foreach ($d['ultimos'] as $a) {
        $linhas .= '<tr><td class="miudo">' . e((string) $a['quando']) . '</td><td>' . e((string) $a['usuario'])
            . '</td><td>' . e((string) $a['acao']) . '</td><td class="miudo">' . e((string) $a['alvo']) . '</td></tr>';
    }
    return '<h1>' . icone_ui('grade', 22) . ' Visão geral</h1>'
        . '<div class="kpis">'
        . '<div class="kpi"><span>' . (int) $k['projetos'] . '</span><small>projetos no acervo</small></div>'
        . '<div class="kpi"><span>' . (int) $k['publicos'] . '</span><small>mostrados ao público</small></div>'
        . '<div class="kpi"><span>' . (int) $k['internos'] . '</span><small>internos</small></div>'
        . '<div class="kpi"><span>' . (int) $k['repos'] . '</span><small>repositórios</small></div>'
        . '<div class="kpi"><span>' . (int) $k['pedidos'] . '</span><small>pedidos novos</small></div>'
        . '<div class="kpi"><span>' . (int) $k['usuarios'] . '</span><small>usuários</small></div></div>'
        . '<h2>Auditoria recente</h2><table class="tabela"><thead><tr><th>quando</th><th>usuário</th><th>ação</th><th>alvo</th></tr></thead>'
        . '<tbody>' . ($linhas ?: '<tr><td colspan="4" class="miudo">sem registros</td></tr>') . '</tbody></table>';
}

function painel_projetos(array $d): string
{
    $linhas = '';
    foreach ($d['projetos'] as $p) {
        $linhas .= '<tr><td>' . icone_projeto((string) $p['categoria'], (string) $p['cor'], 34) . '</td>'
            . '<td><strong>' . e((string) $p['titulo']) . '</strong><br><code class="miudo">/p/' . e((string) $p['slug']) . '</code></td>'
            . '<td class="miudo">' . e((string) $p['categoria']) . '</td>'
            . '<td>' . etiqueta(IN3_NIVEL_ROTULO[IN3_NIVEIS[(string) $p['nivel_divulgacao']] ?? 1] ?? '—', 'nv') . '</td>'
            . '<td>' . etiqueta((string) $p['status'], 'st') . '</td>'
            . '<td>' . ((int) $p['mostrar_ao_publico'] === 1 ? etiqueta('público', 'aberto') : etiqueta('interno', 'interno')) . '</td>'
            . '<td class="miudo">' . e(data_br((string) $p['atualizado_em'])) . '</td>'
            . '<td><a class="botao botao-fraco" href="' . e(url_painel('/projetos/editar/' . (int) $p['id'])) . '">editar</a></td></tr>';
    }
    return '<div class="cabeca-aba"><h1>' . icone_ui('cubo', 22) . ' Projetos</h1>'
        . '<a class="botao botao-forte" href="' . e(url_painel('/projetos/novo')) . '">novo projeto</a></div>'
        . '<p class="miudo">A coluna <em>publicar</em> é a única chave do site público. Todo item nasce interno.</p>'
        . '<table class="tabela"><thead><tr><th></th><th>projeto</th><th>categoria</th><th>teto</th><th>status</th><th>publicar</th><th>atualizado</th><th></th></tr></thead>'
        . '<tbody>' . $linhas . '</tbody></table>';
}

function painel_projeto_form(array $p, array $repos, array $camadas, array $todosRepos, string $msg = ''): string
{
    $novo = empty($p['id']);
    $campo = static function (string $nome, string $rot, string $valor, string $tipo = 'text') : string {
        return '<div class="campo"><label for="' . $nome . '">' . $rot . '</label>'
            . '<input id="' . $nome . '" name="' . $nome . '" type="' . $tipo . '" value="' . e($valor) . '"></div>';
    };
    $selNivel = '';
    foreach (IN3_NIVEL_ROTULO as $n => $rot) {
        $selNivel .= '<option value="' . $n . '"' . ((int) (IN3_NIVEIS[(string) $p['nivel_divulgacao']] ?? 1) === $n ? ' selected' : '') . '>'
            . $n . ' · ' . e($rot) . '</option>';
    }
    $camposCamada = '';
    $mapa = [];
    foreach ($camadas as $c) {
        $mapa[(int) $c['profundidade']] = $c;
    }
    for ($i = 1; $i <= 4; $i++) {
        $c = $mapa[$i] ?? ['titulo' => IN3_NIVEL_ROTULO[$i], 'corpo' => ''];
        $camposCamada .= '<fieldset class="camada-form"><legend>' . $i . ' · ' . e(IN3_NIVEL_ROTULO[$i]) . '</legend>'
            . '<div class="campo"><label for="camada_' . $i . '_titulo">título</label>'
            . '<input id="camada_' . $i . '_titulo" name="camada_' . $i . '_titulo" value="' . e((string) $c['titulo']) . '"></div>'
            . '<div class="campo"><label for="camada_' . $i . '_corpo">conteúdo</label>'
            . '<textarea id="camada_' . $i . '_corpo" name="camada_' . $i . '_corpo" rows="5">' . e((string) $c['corpo']) . '</textarea></div>'
            . '<p class="miudo">' . e(IN3_NIVEL_DESCRICAO[$i]) . '</p></fieldset>';
    }
    $cks = '';
    foreach ($todosRepos as $r) {
        $on = in_array((int) $r['id'], array_map(static fn(array $x): int => (int) $x['id'], $repos), true);
        $cks .= '<label class="ck"><input type="checkbox" name="repos[]" value="' . (int) $r['id'] . '"' . ($on ? ' checked' : '') . '> '
            . '<code>' . e((string) $r['nome']) . '</code> <span class="miudo">' . e((string) $r['visibilidade']) . '</span></label>';
    }
    return '<h1>' . icone_ui('cubo', 22) . ' ' . ($novo ? 'Novo projeto' : 'Editar projeto') . '</h1>'
        . ($msg !== '' ? '<p class="aviso">' . e($msg) . '</p>' : '')
        . '<form class="form-largo" method="post">'
        . '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'
        . '<input type="hidden" name="id" value="' . (int) ($p['id'] ?? 0) . '">'
        . '<div class="linha-campos">' . $campo('titulo', 'Título', (string) ($p['titulo'] ?? ''))
        . $campo('slug', 'Slug (vazio = automático)', (string) ($p['slug'] ?? '')) . '</div>'
        . '<div class="linha-campos">' . $campo('emoji', 'Emoji', (string) ($p['emoji'] ?? '🧊'))
        . $campo('cor', 'Cor (hex)', (string) ($p['cor'] ?? '#2A9581')) . '</div>'
        . '<div class="linha-campos">' . $campo('categoria', 'Categoria', (string) ($p['categoria'] ?? 'Governança & Infra'))
        . $campo('status', 'Status', (string) ($p['status'] ?? 'em incubação')) . '</div>'
        . '<div class="campo"><label for="resumo">Resumo público (uma frase)</label>'
        . '<textarea id="resumo" name="resumo" rows="2">' . e((string) ($p['resumo'] ?? '')) . '</textarea></div>'
        . '<div class="linha-campos"><div class="campo"><label for="nivel_divulgacao">Teto de divulgação</label>'
        . '<select id="nivel_divulgacao" name="nivel_divulgacao">' . $selNivel . '</select></div>'
        . '<div class="campo"><label for="ordem">Ordem</label><input id="ordem" name="ordem" type="number" value="' . (int) ($p['ordem'] ?? 100) . '"></div></div>'
        . '<div class="linha-ck">'
        . '<label class="ck"><input type="checkbox" name="mostrar_ao_publico" value="1"' . ((int) ($p['mostrar_ao_publico'] ?? 0) === 1 ? ' checked' : '') . '> mostrar ao público</label>'
        . '<label class="ck"><input type="checkbox" name="mostrar_link_repo" value="1"' . ((int) ($p['mostrar_link_repo'] ?? 0) === 1 ? ' checked' : '') . '> mostrar endereço dos repositórios</label></div>'
        . '<h2>Repositórios vinculados</h2><div class="lista-ck">' . $cks . '</div>'
        . '<h2>Camadas</h2>' . $camposCamada
        . '<div class="acoes"><button class="botao botao-forte" type="submit">salvar projeto</button>'
        . '<a class="botao" href="' . e(url_painel('/projetos')) . '">voltar</a></div></form>';
}

function painel_pedidos(array $d): string
{
    $linhas = '';
    foreach ($d['pedidos'] as $p) {
        $link = (string) ($p['link'] ?? '');
        $linhas .= '<tr><td>#' . (int) $p['id'] . '</td><td>' . e((string) $p['nome']) . '<br><span class="miudo">' . e((string) $p['email']) . '</span></td>'
            . '<td>' . e((string) ($p['projeto'] ?? '—')) . '</td><td>' . (int) $p['nivel'] . ' · '
            . e(IN3_NIVEL_ROTULO[(int) $p['nivel']] ?? '') . '</td><td class="miudo">' . e(limpar_texto((string) $p['mensagem'], 120)) . '</td>'
            . '<td>' . etiqueta((string) $p['status'], (string) $p['status'] === 'liberado' ? 'aberto' : 'st') . '</td>'
            . '<td>' . ($link !== ''
                ? '<a class="botao botao-fraco" href="' . e($link) . '">link de acesso</a>'
                : '<form method="post" class="inline"><input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'
                  . '<input type="hidden" name="liberar" value="' . (int) $p['id'] . '"><button class="botao botao-forte" type="submit">liberar</button></form>')
            . '</td></tr>';
    }
    return '<h1>' . icone_ui('pasta', 22) . ' Pedidos de detalhamento</h1>'
        . '<p class="miudo">Liberar gera um link temporário <code>/acesso/TOKEN</code> que eleva a clareza só daquele projeto, por 7 dias.</p>'
        . '<table class="tabela"><thead><tr><th>#</th><th>solicitante</th><th>projeto</th><th>nível</th><th>contexto</th><th>status</th><th>ação</th></tr></thead>'
        . '<tbody>' . ($linhas ?: '<tr><td colspan="7" class="miudo">nenhum pedido</td></tr>') . '</tbody></table>';
}

function painel_usuarios(array $d): string
{
    $linhas = '';
    foreach ($d['usuarios'] as $u) {
        $linhas .= '<tr><td>' . e((string) $u['usuario']) . '</td><td>' . e((string) $u['nome']) . '</td>'
            . '<td>' . e(IN3_PAPEL_ROTULO[(string) $u['papel']] ?? '') . '</td><td>' . (int) $u['clareza'] . '</td>'
            . '<td>' . ((int) $u['ativo'] === 1 ? etiqueta('ativo', 'aberto') : etiqueta('inativo', 'interno')) . '</td>'
            . '<td>' . ((int) $u['precisa_trocar_senha'] === 1 ? etiqueta('troca pendente', 'st') : etiqueta('senha própria', 'nv')) . '</td>'
            . '<td class="miudo">' . e((string) $u['ultimo_acesso']) . '</td>'
            . '<td><a class="botao botao-fraco" href="' . e(url_painel('/usuarios/editar/' . (int) $u['id'])) . '">editar</a></td></tr>';
    }
    return '<div class="cabeca-aba"><h1>' . icone_ui('usuario', 22) . ' Usuários</h1>'
        . '<a class="botao botao-forte" href="' . e(url_painel('/usuarios/novo')) . '">novo usuário</a></div>'
        . '<p class="miudo">' . icone_ui('escudo', 15) . ' Nenhuma senha é exibida, exportada ou registrada em log — o banco guarda só o hash.</p>'
        . '<table class="tabela"><thead><tr><th>usuário</th><th>nome</th><th>papel</th><th>clareza</th><th>estado</th><th>senha</th><th>último acesso</th><th></th></tr></thead>'
        . '<tbody>' . $linhas . '</tbody></table>';
}

function painel_usuario_form(array $u, array $perms, string $msg = ''): string
{
    $novo = empty($u['id']);
    $pap = '';
    foreach (IN3_PAPEL_ROTULO as $k => $rot) {
        $pap .= '<option value="' . e($k) . '"' . ((string) ($u['papel'] ?? 'leitor') === $k ? ' selected' : '') . '>' . e($rot) . '</option>';
    }
    $cl = '';
    for ($i = 1; $i <= 4; $i++) {
        $cl .= '<option value="' . $i . '"' . ((int) ($u['clareza'] ?? 1) === $i ? ' selected' : '') . '>' . $i . ' · ' . e(IN3_NIVEL_ROTULO[$i]) . '</option>';
    }
    $mtz = '';
    foreach (IN3_PAPEL_ROTULO as $k => $rot) {
        $r = IN3_PERMISSOES[$k] ?? [];
        $mtz .= '<tr><td><strong>' . e($rot) . '</strong></td><td class="miudo">' . e(implode(' · ', $r)) . '</td></tr>';
    }
    return '<h1>' . icone_ui('usuario', 22) . ' ' . ($novo ? 'Novo usuário' : 'Editar usuário') . '</h1>'
        . ($msg !== '' ? '<p class="aviso">' . e($msg) . '</p>' : '')
        . '<form class="form-largo" method="post">'
        . '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'
        . '<input type="hidden" name="id" value="' . (int) ($u['id'] ?? 0) . '">'
        . '<div class="linha-campos"><div class="campo"><label for="usuario">Usuário</label>'
        . '<input id="usuario" name="usuario" required value="' . e((string) ($u['usuario'] ?? '')) . '"></div>'
        . '<div class="campo"><label for="nome">Nome</label><input id="nome" name="nome" value="' . e((string) ($u['nome'] ?? '')) . '"></div></div>'
        . '<div class="campo"><label for="email">E-mail</label><input id="email" name="email" type="email" value="' . e((string) ($u['email'] ?? '')) . '"></div>'
        . '<div class="linha-campos"><div class="campo"><label for="papel">Papel</label><select id="papel" name="papel">' . $pap . '</select></div>'
        . '<div class="campo"><label for="clareza">Clareza (até que camada lê)</label><select id="clareza" name="clareza">' . $cl . '</select></div></div>'
        . '<div class="campo"><label for="senha">' . ($novo ? 'Senha inicial' : 'Nova senha (vazio = manter)') . '</label>'
        . '<input id="senha" name="senha" type="password" autocomplete="new-password"></div>'
        . '<div class="linha-ck"><label class="ck"><input type="checkbox" name="ativo" value="1"' . ((int) ($u['ativo'] ?? 1) === 1 ? ' checked' : '') . '> ativo</label>'
        . '<label class="ck"><input type="checkbox" name="exigir_troca" value="1"> exigir troca no próximo acesso</label></div>'
        . '<div class="acoes"><button class="botao botao-forte" type="submit">salvar usuário</button>'
        . '<a class="botao" href="' . e(url_painel('/usuarios')) . '">voltar</a></div></form>'
        . '<h2>Matriz de permissões (somente leitura)</h2><table class="tabela"><thead><tr><th>papel</th><th>pode</th></tr></thead><tbody>' . $mtz . '</tbody></table>';
}

function painel_sistema(array $d): string
{
    $c = $d['cob'];
    $orfaos = $c['orfaos'] ? e(implode(', ', $c['orfaos'])) : 'nenhum';
    $linhas = '';
    foreach ($d['repos'] as $r) {
        $linhas .= '<tr><td><code>' . e((string) $r['nome']) . '</code></td>'
            . '<td>' . etiqueta((string) $r['visibilidade'], (string) $r['visibilidade'] === 'publico' ? 'aberto' : 'interno') . '</td>'
            . '<td class="miudo">' . e((string) $r['linguagem']) . '</td>'
            . '<td class="miudo">' . e(limpar_texto((string) $r['descricao'], 70)) . '</td>'
            . '<td class="miudo">' . e(data_br((string) $r['gh_atualizado_em'])) . '</td>'
            . '<td>' . (int) $r['n_projetos'] . '</td></tr>';
    }
    return '<h1>' . icone_ui('escudo', 22) . ' Sistema</h1>'
        . '<div class="kpis"><div class="kpi"><span>' . (int) $c['total'] . '</span><small>repositórios inventariados</small></div>'
        . '<div class="kpi"><span>' . (int) $c['mapeados'] . '/' . (int) $c['total'] . '</span><small>cobertura de mapeamento</small></div>'
        . '<div class="kpi"><span>' . (int) $c['publicos'] . '</span><small>repositórios públicos</small></div>'
        . '<div class="kpi"><span>' . (int) $c['privados'] . '</span><small>privados confirmados</small></div></div>'
        . '<p class="miudo">Sem token do GitHub a API pública não devolve repositórios privados: eles ficam como <em>pendente</em>, nunca inventados.</p>'
        . '<p class="miudo">Repositórios sem projeto vinculado: ' . $orfaos . '</p>'
        . '<h2>Inventário</h2><table class="tabela"><thead><tr><th>repositório</th><th>escopo</th><th>linguagem</th><th>descrição</th><th>atualizado</th><th>projetos</th></tr></thead>'
        . '<tbody>' . $linhas . '</tbody></table>'
        . '<h2>Manutenção por linha de comando</h2><pre class="codigo">php tools/instalar.php   # cria/atualiza o banco e o acervo'
        . "\n" . 'php tools/sincronizar.php --conta=USUARIO [--token=ghp_xxx]   # varre os repositórios'
        . "\n" . 'php tools/semear.php     # remapeia repositório → projeto'
        . "\n" . 'php tools/verificar.php  # prova que nada interno vazou'
        . "\n" . 'php tools/exportar.php   # gera versão estática do site público</pre>';
}

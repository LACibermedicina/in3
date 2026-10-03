<?php
declare(strict_types=1);
/**
 * =====================================================================
 * IN³ · vistas v4 — seletor imersivo, Kanban, hotsite com mockup,
 * painel de idiomas e prompt de sugestão. Camada nova sobre as vistas v3.
 * =====================================================================
 */

/** Seletor imersivo de projetos: grade de cubos interativos (nunca <select>). */
function vista_seletor(array $projetos, string $titulo = 'Escolha o projeto'): string
{
    $h = '<section class="seletor" id="in3-seletor" aria-labelledby="seletor-t">'
        . '<div class="seletor-cabeca"><h2 id="seletor-t">' . icone_ui('grade', 20) . e($titulo) . '</h2>'
        . '<input class="seletor-busca" type="search" id="seletor-filtro" placeholder="filtrar por nome, categoria ou tecnologia"'
        . ' aria-label="Filtrar projetos" autocomplete="off"></div>'
        . '<div class="seletor-grade" role="listbox" aria-label="Projetos do acervo" id="seletor-grade">';
    foreach ($projetos as $p) {
        $h .= '<a class="cubo-op" role="option" aria-selected="false" tabindex="0"'
            . ' href="' . e(url('/p/' . $p['slug'])) . '"'
            . ' data-nome="' . e(mb_strtolower((string) $p['titulo'] . ' ' . $p['categoria'] . ' ' . $p['status'])) . '"'
            . ' style="--cor:' . e((string) $p['cor']) . '">'
            . '<span class="cubo-face" aria-hidden="true">' . icone_projeto((string) $p['categoria'], (string) $p['cor'], 46) . '</span>'
            . '<span class="cubo-txt"><strong>' . e((string) $p['titulo']) . '</strong>'
            . '<em>' . e((string) $p['categoria']) . ' · ' . e((string) $p['status']) . '</em></span>'
            . '<span class="cubo-seta" aria-hidden="true">→</span></a>';
    }
    return $h . '</div><p class="miudo centro seletor-dica">'
        . count($projetos) . ' projeto(s) · clique no cubo ou use as setas do teclado (← → ↑ ↓ + Enter).</p></section>';
}

/** Hotsite imersivo: mockup do produto desenhado em CSS/SVG, sem imagem externa. */
function vista_mockup(array $p): string
{
    $cor = (string) $p['cor'];
    $titulo = (string) $p['titulo'];
    $resumo = (string) $p['resumo'];
    $telas = [
        ['rotulo' => 'Visão geral', 'linhas' => 3],
        ['rotulo' => 'Fluxo principal', 'linhas' => 4],
        ['rotulo' => 'Dados', 'linhas' => 2],
    ];
    $h = '<section class="mockup" id="mockup" style="--cor:' . e($cor) . '">'
        . '<h2>' . icone_ui('cubo', 20) . 'Mockup imersivo</h2>'
        . '<p class="miudo">Representação visual do produto, montada com as cores e o ícone do projeto — '
        . 'nada de captura de tela real, para não expor dado interno.</p>'
        . '<div class="mockup-palco">'
        . '<div class="mockup-janela" role="img" aria-label="Mockup ilustrativo de ' . e($titulo) . '">'
        . '<div class="mj-barra"><span></span><span></span><span></span>'
        . '<b>' . e(strtolower($titulo)) . '.m3d.pro</b></div>'
        . '<div class="mj-corpo">'
        . '<aside class="mj-lado">' . icone_projeto((string) $p['categoria'], $cor, 34)
        . '<i></i><i></i><i></i><i class="on"></i><i></i></aside>'
        . '<div class="mj-tela">'
        . '<div class="mj-hero"><span class="mj-selo">' . e((string) $p['status']) . '</span>'
        . '<strong>' . e($titulo) . '</strong><p>' . e(limpar_texto($resumo, 130)) . '</p></div>'
        . '<div class="mj-cartoes">';
    foreach ($telas as $t) {
        $h .= '<div class="mj-cartao"><b>' . e($t['rotulo']) . '</b>';
        for ($i = 0; $i < (int) $t['linhas']; $i++) {
            $h .= '<em style="width:' . (92 - $i * 14) . '%"></em>';
        }
        $h .= '</div>';
    }
    $h .= '</div></div></div></div>'
        . '<div class="mockup-celular" role="img" aria-label="Versão móvel do mockup">'
        . '<div class="mc-alto"><span class="mc-cam"></span>'
        . icone_projeto((string) $p['categoria'], $cor, 30)
        . '<em></em><em></em><em style="width:62%"></em>'
        . '<div class="mc-fab" style="background:' . e($cor) . '">+</div></div></div>'
        . '</div></section>';
    return $h;
}

/** Prompt de sugestão com dropdown imersivo (sem <select>). */
function vista_sugestao(array $projetos, string $msg = ''): string
{
    $atual = (int) ($_GET['projeto'] ?? 0);
    $h = '<section class="sugestao" id="sugestao">'
        . '<h2>' . icone_ui('cubo', 20) . 'Sugerir um projeto</h2>'
        . '<p class="miudo">Sua ideia entra como <strong>sugerido</strong> no Kanban do projeto escolhido e aparece '
        . 'também no backlog geral, carimbada com o ícone desse projeto.</p>'
        . ($msg !== '' ? '<p class="aviso">' . e($msg) . '</p>' : '')
        . '<form class="form-sugestao" method="post" action="' . e(url('/sugestao')) . '">'
        . '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'
        . '<input type="hidden" name="projeto" id="sug-projeto" value="' . $atual . '">'
        . '<div class="campo"><label id="sug-rot">Projeto</label>'
        . '<div class="dropdown" id="sug-dropdown">'
        . '<button type="button" class="dd-botao" id="sug-botao" aria-haspopup="listbox" aria-expanded="false">'
        . '<span id="sug-escudo" class="dd-escudo"></span>'
        . '<span id="sug-nome" class="dd-nome">Escolha o projeto…</span>'
        . '<span class="dd-seta" aria-hidden="true">▾</span></button>'
        . '<div class="dd-painel" id="sug-painel" role="listbox" aria-labelledby="sug-rot" hidden>'
        . '<input class="dd-busca" id="sug-busca" type="search" placeholder="digitar para filtrar…" aria-label="Filtrar projetos">'
        . '<div class="dd-lista" id="sug-lista">';
    foreach ($projetos as $p) {
        $sel = ((int) $p['id'] === $atual) ? ' selecionado' : '';
        $h .= '<button type="button" class="dd-item' . $sel . '" role="option" aria-selected="' . ($sel ? 'true' : 'false') . '"'
            . ' data-id="' . (int) $p['id'] . '" data-nome="' . e((string) $p['titulo']) . '"'
            . ' data-busca="' . e(mb_strtolower((string) $p['titulo'] . ' ' . $p['categoria'])) . '"'
            . ' data-icone="' . e(icone_projeto((string) $p['categoria'], (string) $p['cor'], 26)) . '">'
            . icone_projeto((string) $p['categoria'], (string) $p['cor'], 26)
            . '<span><strong>' . e((string) $p['titulo']) . '</strong><em>' . e((string) $p['categoria']) . '</em></span></button>';
    }
    $h .= '</div></div></div></div>'
        . '<div class="campo"><label for="sug-titulo">Ideia</label>'
        . '<input id="sug-titulo" name="titulo" required minlength="4" maxlength="160" placeholder="ex.: painel de exames por voz"></div>'
        . '<div class="campo"><label for="sug-detalhe">Detalhe</label>'
        . '<textarea id="sug-detalhe" name="detalhe" rows="4" maxlength="1200" placeholder="o que resolveria, para quem, e por que agora"></textarea></div>'
        . '<div class="campo"><label for="sug-contato">Contato (opcional)</label>'
        . '<input id="sug-contato" name="contato" maxlength="160" placeholder="e-mail ou usuário"></div>'
        . '<button class="botao botao-forte" type="submit">Enviar sugestão</button>'
        . '<p class="miudo">A sugestão não publica nada: ela entra na fila de curadoria do projeto escolhido.</p>'
        . '</form></section>';
    return $h;
}

/** Kanban (usado no painel): colunas + cartões, com arrastar-e-soltar. */
function vista_kanban(array $colunas, array $projetos, string $titulo, string $voltar = ''): string
{
    $h = '<div class="kanban-topo"><h1>' . icone_ui('grade', 22) . ' ' . e($titulo) . '</h1>';
    if ($voltar !== '') {
        $h .= '<a class="botao" href="' . e($voltar) . '">← backlog geral por projeto</a>';
    }
    $h .= '</div><div class="kanban" id="in3-kanban">';
    foreach ($colunas as $chave => $col) {
        $h .= '<section class="kb-col" data-col="' . e((string) $chave) . '" style="--kb:' . e((string) $col['cor']) . '">'
            . '<header><span class="kb-pino"></span><strong>' . e((string) $col['rotulo']) . '</strong>'
            . '<i class="kb-n">' . count($col['cartoes']) . '</i></header><div class="kb-corpo">';
        foreach ($col['cartoes'] as $c) {
            $pid = (int) $c['projeto_id'];
            $h .= '<article class="kb-cartao" draggable="true" data-id="' . (int) $c['id'] . '" style="--cor:'
                . e((string) ($c['cor'] ?? '#2A9581')) . '">'
                . '<span class="kb-icone" title="' . e((string) ($c['projeto'] ?? 'geral')) . '">'
                . icone_projeto((string) ($c['icone'] ?? 'geral'), (string) ($c['cor'] ?? '#2A9581'), 26) . '</span>'
                . '<div class="kb-txt"><strong>' . e((string) $c['titulo']) . '</strong>'
                . '<em>' . e((string) ($c['projeto'] ?? 'sem projeto')) . ' · #' . (int) $c['id'] . '</em>'
                . ($c['detalhe'] !== '' ? '<p>' . e(limpar_texto((string) $c['detalhe'], 150)) . '</p>' : '')
                . ($c['contato'] !== '' ? '<span class="miudo">contato: ' . e((string) $c['contato']) . '</span>' : '')
                . '</div></article>';
        }
        $h .= '</div></section>';
    }
    return $h . '</div><p class="miudo">Arraste o cartão entre colunas. Cada mudança vai para a auditoria.</p>';
}

/** Cartões de projeto com contagem no backlog (backlog geral por ícone). */
function vista_backlog_geral(array $projetos, array $contagem): string
{
    $h = '<h2>' . icone_ui('grafo', 20) . 'Backlog geral por projeto</h2><div class="backlog-grade">';
    foreach ($projetos as $p) {
        $id = (int) $p['id'];
        $n = (int) ($contagem[$id]['total'] ?? 0);
        $abertos = (int) ($contagem[$id]['abertos'] ?? 0);
        $h .= '<a class="backlog-cartao" href="' . e(url_painel('/backlog/' . $id)) . '" style="--cor:' . e((string) $p['cor']) . '">'
            . icone_projeto((string) $p['categoria'], (string) $p['cor'], 40)
            . '<span><strong>' . e((string) $p['titulo']) . '</strong>'
            . '<em>' . $n . ' sugestão(ões) · ' . $abertos . ' em aberto</em></span></a>';
    }
    return $h . '</div>';
}

function contagem_por_projeto(): array
{
    $out = [];
    foreach (consulta("SELECT projeto_id, count(*) AS total,
                       sum(CASE WHEN status IN ('sugerido','analise','backlog') THEN 1 ELSE 0 END) AS abertos
                       FROM sugestoes GROUP BY projeto_id") as $l) {
        $out[(int) $l['projeto_id']] = ['total' => (int) $l['total'], 'abertos' => (int) $l['abertos']];
    }
    return $out;
}

/** Formulário de vínculo usuário × projeto (RBAC de escopo). */
function vista_usuario_projetos(array $u, array $projetos): string
{
    $vinculos = projetos_do_usuario((int) $u['id']);
    $h = '<h2>' . icone_ui('usuario', 20) . 'Projetos visíveis para ' . e((string) $u['usuario']) . '</h2>'
        . '<p class="miudo">Sem nenhum vínculo, o usuário herda o papel e vê o acervo conforme a clareza. '
        . 'Com vínculo, ele vê <strong>somente</strong> os projetos marcados abaixo.</p>'
        . '<form method="post" action="' . e(url_painel('/usuarios/' . (int) $u['id'] . '/projetos')) . '">'
        . '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'
        . '<div class="lista-ck projetos-ck">';
    foreach ($projetos as $p) {
        $on = isset($vinculos[(int) $p['id']]);
        $h .= '<label class="ck ck-cubo" style="--cor:' . e((string) $p['cor']) . '">'
            . '<input type="checkbox" name="projetos[]" value="' . (int) $p['id'] . '"' . ($on ? ' checked' : '') . '>'
            . icone_projeto((string) $p['categoria'], (string) $p['cor'], 24)
            . '<span>' . e((string) $p['titulo']) . '</span></label>';
    }
    $h .= '</div><div class="campo campo-curto"><label for="nivel">Nível máximo (clareza) neste vínculo</label>'
        . '<select id="nivel" name="nivel">';
    foreach ([1, 2, 3, 4] as $n) {
        $h .= '<option value="' . $n . '">' . $n . ' · ' . e((string) IN3_NIVEL_ROTULO[$n]) . '</option>';
    }
    $h .= '</select></div><button class="botao botao-forte" type="submit">gravar vínculos</button></form>';
    return $h;
}

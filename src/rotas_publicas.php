<?php
declare(strict_types=1);
/**
 * =====================================================================
 * IN³ · rotas do site público
 * Nenhuma referência à área administrativa sai daqui.
 * v4: + seletor imersivo · mockup no hotsite · sugestão com dropdown
 * =====================================================================
 */

function rota_publica_despachar(string $uri, string $metodo): void
{
    $cl = clareza_efetiva();
    $admin = pode('busca.ampla');

    switch (true) {
        /* ---------------------------------------------------------- home */
        case $uri === '/':
            $projetos = projetos_visiveis($cl, false);
            $tec = tecnologias_publicas($projetos);
            $corpo = vista_inicio([
                'projetos'    => $projetos,
                'feed'        => atualizacoes_recentes($cl, 6),
                'tecnologias' => $tec,
                'n_publicos'  => count($projetos),
                'n_tec'       => count($tec),
                'cob'         => cobertura_repos(),
            ])
            . vista_seletor($projetos, 'Escolha o projeto')
            . vista_sugestao($projetos, (string) ($_SESSION['msg_sugestao'] ?? ''));
            unset($_SESSION['msg_sugestao']);
            echo layout('Portfólio vivo da incubadora', $corpo);
            return;

        /* -------------------------------------------------- hotsite /p/  */
        case str_starts_with($uri, '/p/'):
            $slug = substr($uri, 3);
            $p = projeto_por_slug($slug);
            if (!$p) {
                nao_encontrado('Projeto não encontrado no acervo público.');
                return;
            }
            if ((int) $p['mostrar_ao_publico'] !== 1 && !pode('projeto.ver')) {
                nao_encontrado('Este item está sob curadoria interna.');
                return;
            }
            if (!pode('*') && !pode('projeto.ver') && !projeto_autorizado((int) $p['id'])) {
                nao_encontrado('Este projeto não está vinculado à sua conta.');
                return;
            }
            $clP = clareza_projeto((int) $p['id']);
            $u = usuario_atual();
            if ($u) {
                $clP = max($clP, nivel_usuario_projeto((int) $u['id'], (int) $p['id'], $clP));
            }
            if ($admin) {
                $clP = 4;
            }
            $m = camadas_do_projeto($p, $clP, false);
            $rel = array_slice(array_values(array_filter(projetos_visiveis($cl),
                static fn(array $x): bool => (int) $x['id'] !== (int) $p['id']
                    && (string) $x['categoria'] === (string) $p['categoria'])), 0, 3);
            if (count($rel) === 0) {
                $rel = array_slice(array_values(array_filter(projetos_visiveis($cl),
                    static fn(array $x): bool => (int) $x['id'] !== (int) $p['id'])), 0, 3);
            }
            echo layout((string) $p['titulo'],
                vista_mockup($p)
                . vista_projeto($p, $m, repos_do_projeto((int) $p['id']), $rel, $clP, pode('projeto.editar'))
                . vista_seletor($rel ?: projetos_visiveis($cl), 'Outros projetos do acervo'));
            return;

        /* ------------------------------------------------------- busca */
        case $uri === '/busca':
            $q = trim((string) ($_GET['q'] ?? ''));
            echo layout('Busca', vista_busca($q, buscar($q, $cl, $admin), $cl));
            return;

        /* -------------------------------------------------- tecnologias */
        case $uri === '/tecnologias':
            echo layout('Tecnologias', vista_tecnologias(tecnologias_publicas(projetos_visiveis($cl))));
            return;

        /* -------------------------------------------------- sugestão */
        case $uri === '/sugestao' || $uri === '/sugerir':
            $msg = '';
            if ($metodo === 'POST') {
                if (!csrf_valido($_POST['csrf'] ?? null)) {
                    $msg = 'Sessão expirada. Recarregue a página e tente de novo.';
                } else {
                    $titulo = trim((string) ($_POST['titulo'] ?? ''));
                    $pid = (int) ($_POST['projeto'] ?? 0);
                    if ($titulo === '' || mb_strlen($titulo) < 4) {
                        $msg = 'Descreva a ideia em pelo menos 4 caracteres.';
                    } elseif ($pid <= 0 || !um('SELECT id FROM projetos WHERE id = :i', [':i' => $pid])) {
                        $msg = 'Escolha o projeto no menu imersivo.';
                    } else {
                        $id = sugestao_criar($pid, $titulo, (string) ($_POST['detalhe'] ?? ''),
                            (string) ($_POST['contato'] ?? ''), 'publico');
                        $_SESSION['msg_sugestao'] = 'Sugestão #' . $id . ' registrada. Ela entrou no Kanban do projeto '
                            . 'e no backlog geral, aguardando curadoria.';
                        header('Location: ' . url('/#sugestao'), true, 302);
                        exit;
                    }
                }
            }
            echo layout('Sugerir um projeto', vista_sugestao(projetos_visiveis($cl), $msg));
            return;

        /* -------------------------------------------- pedido de detalhe */
        case $uri === '/pedido':
            $msg = '';
            if ($metodo === 'POST') {
                if (!csrf_valido($_POST['csrf'] ?? null)) {
                    $msg = 'Sessão expirada. Recarregue a página e tente de novo.';
                } else {
                    $idProj = (int) ($_POST['projeto'] ?? 0);
                    $nome = trim((string) ($_POST['nome'] ?? ''));
                    $mail = trim((string) ($_POST['email'] ?? ''));
                    if ($nome === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                        $msg = 'Informe nome e um e-mail válido.';
                    } else {
                        $id = criar_pedido($nome, $mail, $idProj, (int) ($_POST['nivel'] ?? 3), (string) ($_POST['mensagem'] ?? ''));
                        $msg = 'Pedido #' . $id . ' registrado. O curador libera o nível e o link chega em ' . $mail . '.';
                    }
                }
            }
            echo layout('Pedir detalhamento', vista_pedido(projetos_visiveis($cl), $msg,
                (string) ($_GET['projeto'] ?? ''), (int) ($_GET['nivel'] ?? 3)));
            return;

        /* ------------------------------------------------ link temporário */
        case str_starts_with($uri, '/acesso/'):
            $a = abrir_acesso(substr($uri, 8));
            if (!$a) {
                nao_encontrado('Link de acesso inválido, já usado ou expirado.');
                return;
            }
            $p = projeto_por_id((int) $a['projeto_id']);
            if (!$p) {
                nao_encontrado('Projeto indisponível.');
                return;
            }
            $m = camadas_do_projeto($p, max((int) $a['nivel'], clareza_projeto((int) $p['id'])));
            echo layout((string) $p['titulo'], '<p class="aviso">Acesso liberado até a camada ' . (int) $a['nivel']
                . ' para este projeto. Válido até ' . e((string) $a['expira_em']) . '.</p>'
                . vista_mockup($p)
                . vista_projeto($p, $m, repos_do_projeto((int) $p['id']), [], (int) $a['nivel'], false));
            return;

        /* ------------------------------------------------------ suporte */
        case $uri === '/robots.txt':
            header('Content-Type: text/plain; charset=utf-8');
            echo "User-agent: *\nAllow: /\nDisallow: /acesso/\nSitemap: " . url('/sitemap.xml') . "\n";
            return;

        case $uri === '/sitemap.xml':
            header('Content-Type: application/xml; charset=utf-8');
            $out = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach (['/', '/busca', '/tecnologias', '/pedido', '/sugestao'] as $r) {
                $out .= '<url><loc>' . e(url($r)) . '</loc></url>';
            }
            foreach (projetos_visiveis($cl) as $p) {
                $out .= '<url><loc>' . e(url('/p/' . $p['slug'])) . '</loc></url>';
            }
            echo $out . '</urlset>';
            return;

        case $uri === '/saude':
            responder_json(['ok' => true, 'versao' => IN3_VERSAO, 'quando' => agora(),
                'clareza' => $cl, 'idioma' => idioma_atual(), 'i18n' => i18n_estatisticas()]);
            return;

        default:
            nao_encontrado('Página não encontrada.');
    }
}

/** Tecnologias declaradas apenas em projetos visíveis. */
function tecnologias_publicas(array $projetos): array
{
    $tec = [];
    foreach ($projetos as $p) {
        foreach (tecnologias_do_projeto((int) $p['id']) as $t) {
            $tec[$t] = ($tec[$t] ?? 0) + 1;
        }
    }
    arsort($tec);
    return $tec;
}

function nao_encontrado(string $msg): void
{
    http_response_code(404);
    echo layout('Não encontrado', '<section class="pagina"><h1>404</h1><p>' . e($msg) . '</p>'
        . '<p><a class="botao" href="' . e(url('/')) . '">Voltar ao portfólio</a></p></section>');
}

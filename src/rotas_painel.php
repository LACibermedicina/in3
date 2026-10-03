<?php
declare(strict_types=1);
/**
 * =====================================================================
 * IN³ · rotas do painel (rota apartada, noindex, fora do site público)
 * Toda permissão é checada NO SERVIDOR antes de qualquer leitura/escrita.
 * =====================================================================
 */

function rota_painel_despachar(string $uri, string $rota, string $metodo): void
{
    $base = '/' . $rota;
    $caminho = trim(substr($uri, strlen($base)), '/');
    $partes = $caminho === '' ? [] : explode('/', $caminho);
    $u = usuario_atual();

    /* ------------------------------------------------------------ entrar */
    if ($caminho === 'entrar') {
        if ($metodo === 'POST') {
            if (!csrf_valido($_POST['csrf'] ?? null)) {
                echo layout('Entrar', vista_entrar('Sessão expirada. Recarregue a página.'), ['admin' => true]);
                return;
            }
            $r = login((string) ($_POST['usuario'] ?? ''), (string) ($_POST['senha'] ?? ''));
            if (!$r['ok']) {
                echo layout('Entrar', vista_entrar((string) $r['msg']), ['admin' => true]);
                return;
            }
            redirecionar(url_painel(!empty($r['precisa_trocar']) ? '/senha' : '/'));
        }
        if ($u) {
            redirecionar(url_painel(!empty($u['precisa_trocar_senha']) ? '/senha' : '/'));
        }
        echo layout('Entrar', vista_entrar(), ['admin' => true]);
        return;
    }

    if ($caminho === 'sair') {
        logout();
        redirecionar(url_painel('/entrar'));
    }

    /* ---------------------------------------------- exigência de sessão */
    if (!$u) {
        http_response_code(401);
        echo layout('Acesso restrito', vista_entrar('Faça login para continuar.'), ['admin' => true]);
        return;
    }

    if ((int) $u['precisa_trocar_senha'] === 1 && $caminho !== 'senha') {
        redirecionar(url_painel('/senha'));
    }

    if ($caminho === 'senha') {
        $msg = '';
        if ($metodo === 'POST') {
            if (!csrf_valido($_POST['csrf'] ?? null)) {
                $msg = 'Sessão expirada.';
            } else {
                $r = trocar_senha((string) ($_POST['nova'] ?? ''), (string) ($_POST['confirma'] ?? ''));
                $msg = (string) $r['msg'];
                if ($r['ok']) {
                    redirecionar(url_painel('/'));
                }
            }
        }
        echo layout('Definir senha', vista_trocar_senha($msg), ['admin' => true]);
        return;
    }

    /* ------------------------------------------------- API do painel */
    if (str_starts_with($caminho, 'api/')) {
        $alvo = substr($caminho, 4);
        if ($alvo === 'portfolio') {
            exigir_papel('projeto.ver');
            responder_json(['ok' => true, 'projetos' => projetos_visiveis(4, true), 'quando' => agora()]);
            return;
        }
        if ($alvo === 'pedidos') {
            exigir_papel('pedido.ver');
            responder_json(['ok' => true, 'pedidos' => consulta('SELECT * FROM pedidos ORDER BY id DESC')]);
            return;
        }
        if ($alvo === 'kanban') {
            exigir_papel('projeto.editar');
            $d = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($d)) {
                $d = $_POST;
            }
            if (!csrf_valido($d['csrf'] ?? null)) {
                responder_json(['ok' => false, 'erro' => 'csrf'], 403);
                return;
            }
            $ok = sugestao_mover((int) ($d['id'] ?? 0), (string) ($d['status'] ?? ''));
            responder_json(['ok' => $ok, 'status' => (string) ($d['status'] ?? ''),
                'contagem' => sugestoes_contagem()]);
            return;
        }
        if ($alvo === 'backlog') {
            exigir_papel('pedido.ver');
            responder_json(['ok' => true, 'resumo' => backlog_resumo(),
                'sugestoes' => sugestoes_listar(), 'por_projeto' => contagem_por_projeto()]);
            return;
        }
        responder_json(['erro' => 'rota de API desconhecida'], 404);
        return;
    }

    /* ------------------------------------------------------- navegação */
    switch ($partes[0] ?? '') {

        case 'projetos':
            exigir_papel('projeto.ver');

            if (($partes[1] ?? '') === 'novo') {
                $msg = '';
                if ($metodo === 'POST') {
                    $msg = salvar_projeto($_POST);
                }
                $corpo = vista_projeto_form([], [], [],
                    consulta('SELECT * FROM repos ORDER BY nome COLLATE NOCASE'), $msg);
                echo layout('Novo projeto', vista_painel('projetos', ['form' => $corpo]), ['admin' => true]);
                return;
            }

            if (($partes[1] ?? '') === 'editar' && isset($partes[2])) {
                $p = projeto_por_id((int) $partes[2]);
                if (!$p) {
                    nao_encontrado('Projeto inexistente.');
                    return;
                }
                $msg = '';
                if ($metodo === 'POST') {
                    $_POST['id'] = (int) $p['id'];
                    $msg = salvar_projeto($_POST);
                    $p = projeto_por_id((int) $p['id']);
                }
                $camadas = consulta('SELECT * FROM camadas WHERE projeto_id = :p ORDER BY profundidade', [':p' => (int) $p['id']]);
                $corpo = vista_projeto_form($p, repos_do_projeto((int) $p['id']), $camadas,
                    consulta('SELECT * FROM repos ORDER BY nome COLLATE NOCASE'), $msg);
                echo layout('Editar projeto', vista_painel('projetos', ['form' => $corpo]), ['admin' => true]);
                return;
            }

            echo layout('Projetos', vista_painel('projetos', ['projetos' => consulta('SELECT * FROM projetos ORDER BY mostrar_ao_publico DESC, ordem, titulo')]), ['admin' => true]);
            return;

        case 'pedidos':
            exigir_papel('pedido.ver');
            if ($metodo === 'POST' && !empty($_POST['liberar'])) {
                if (csrf_valido($_POST['csrf'] ?? null)) {
                    $t = liberar_pedido((int) $_POST['liberar']);
                    if ($t !== null) {
                        $_SESSION['ultimo_link'] = url('/acesso/' . $t);
                    }
                }
            }
            $pedidos = consulta('SELECT pe.*, p.titulo AS projeto, p.slug FROM pedidos pe
                                 LEFT JOIN projetos p ON p.id = pe.projeto_id ORDER BY pe.id DESC');
            if (!empty($_SESSION['ultimo_link'])) {
                $pedidos[0]['link'] = (string) $_SESSION['ultimo_link'];
            }
            echo layout('Pedidos', vista_painel('pedidos', ['pedidos' => $pedidos]), ['admin' => true]);
            return;

        case 'usuarios':
            exigir_papel('usuario.ver');
            if (($partes[2] ?? '') === 'projetos' && isset($partes[1])) {
                $uAlvo = um('SELECT * FROM usuarios WHERE id = :i', [':i' => (int) $partes[1]]);
                if (!$uAlvo) {
                    nao_encontrado('Usuário inexistente.');
                    return;
                }
                if ($metodo === 'POST' && csrf_valido($_POST['csrf'] ?? null)) {
                    vincular_usuario_projetos((int) $uAlvo['id'], (array) ($_POST['projetos'] ?? []), (int) ($_POST['nivel'] ?? 4));
                    redirecionar(url_painel('/usuarios'));
                }
                echo layout('Projetos do usuário', vista_painel('usuarios', ['form' => vista_usuario_projetos(
                    $uAlvo, consulta('SELECT * FROM projetos ORDER BY categoria, titulo COLLATE NOCASE'))]), ['admin' => true]);
                return;
            }
            if (($partes[1] ?? '') === 'novo' || (($partes[1] ?? '') === 'editar' && isset($partes[2]))) {
                $editando = ($partes[1] === 'editar');
                $uAlvo = $editando ? um('SELECT * FROM usuarios WHERE id = :i', [':i' => (int) $partes[2]]) : [];
                if ($editando && !$uAlvo) {
                    nao_encontrado('Usuário inexistente.');
                    return;
                }
                $msg = '';
                if ($metodo === 'POST') {
                    $msg = salvar_usuario($_POST, $uAlvo ?: null);
                    if ($editando) {
                        $uAlvo = um('SELECT * FROM usuarios WHERE id = :i', [':i' => (int) $partes[2]]);
                    }
                }
                echo layout('Usuário', vista_painel('usuarios',
                    ['form' => vista_usuario_form($uAlvo ?: [], [], $msg)]), ['admin' => true]);
                return;
            }
            echo layout('Usuários', vista_painel('usuarios', ['usuarios' => consulta('SELECT * FROM usuarios ORDER BY papel, usuario')]), ['admin' => true]);
            return;

        case 'busca':
            $q = trim((string) ($_GET['q'] ?? ''));
            $cl = clareza_efetiva();
            $res = buscar($q, $cl, true);
            $linhas = '';
            foreach ($res['resultados'] as $r) {
                $linhas .= '<li><a href="' . e(url('/p/' . $r['slug'])) . '"><strong>' . e((string) $r['titulo']) . '</strong></a> '
                    . etiqueta('camada ' . (int) $r['profundidade'] . ' · ' . (string) $r['rotulo'], 'nv')
                    . '<p class="miudo">' . e((string) $r['trecho']) . '</p></li>';
            }
            $html = '<h1>' . icone_ui('busca', 22) . ' Busca interna</h1>'
                . '<p class="miudo">Você consulta com a clareza ' . (int) $cl . ' (até a camada ' . (int) $cl . '). '
                . 'Itens internos aparecem aqui, nunca no site público.</p>'
                . '<form class="form-larga" method="get" action="' . e(url_painel('/busca')) . '">'
                . '<div class="campo"><label for="q">termo</label><input id="q" name="q" value="' . e($q) . '" required minlength="2"></div>'
                . '<button class="botao botao-forte" type="submit">buscar</button></form>'
                . ($q === '' ? '' : '<p><strong>' . (int) $res['total'] . '</strong> resultado(s) · escopo máximo alcançado: camada ' . (int) $res['escopo_max'] . '</p>'
                    . '<ul class="resultados">' . ($linhas ?: '<li class="miudo">nada encontrado</li>') . '</ul>');
            echo layout('Busca interna', vista_painel('busca', ['busca_html' => $html]), ['admin' => true]);
            return;

        case 'sistema':
            exigir_papel('sistema.ver');
            echo layout('Sistema', vista_painel('sistema', [
                'cob'   => cobertura_repos(),
                'repos' => consulta('SELECT r.*, (SELECT count(*) FROM projeto_repos pr WHERE pr.repo_id = r.id) AS n_projetos
                                     FROM repos r ORDER BY r.nome COLLATE NOCASE'),
            ]), ['admin' => true]);
            return;

        case '':
            echo layout('Painel', vista_painel('', [
                'kpis' => [
                    'projetos' => (int) escalar('SELECT count(*) FROM projetos'),
                    'publicos' => (int) escalar('SELECT count(*) FROM projetos WHERE mostrar_ao_publico = 1'),
                    'internos' => (int) escalar('SELECT count(*) FROM projetos WHERE mostrar_ao_publico = 0'),
                    'repos'    => (int) escalar('SELECT count(*) FROM repos'),
                    'pedidos'  => (int) escalar("SELECT count(*) FROM pedidos WHERE status = 'novo'"),
                    'usuarios' => (int) escalar('SELECT count(*) FROM usuarios WHERE ativo = 1'),
                    'sugestoes' => (int) escalar('SELECT count(*) FROM sugestoes'),
                    'sugeridos' => (int) escalar("SELECT count(*) FROM sugestoes WHERE status IN ('sugerido','analise')"),
                ],
                'ultimos' => consulta('SELECT * FROM auditoria ORDER BY id DESC LIMIT 12'),
            ]), ['admin' => true]);
            return;

        case 'backlog':
            exigir_papel('pedido.ver');
            if (isset($partes[1]) && ctype_digit((string) $partes[1])) {
                $pid = (int) $partes[1];
                $p = projeto_por_id($pid);
                if (!$p) {
                    nao_encontrado('Projeto inexistente.');
                    return;
                }
                echo layout('Kanban · ' . $p['titulo'], vista_painel('backlog', ['form' => vista_kanban(
                    kanban_montar($pid), consulta('SELECT * FROM projetos'), (string) $p['titulo'], url_painel('/backlog'))]), ['admin' => true]);
                return;
            }
            echo layout('Backlog geral', vista_painel('backlog', ['form' =>
                vista_backlog_geral(consulta('SELECT * FROM projetos ORDER BY categoria, titulo COLLATE NOCASE'), contagem_por_projeto())
                . vista_kanban(kanban_montar(null), consulta('SELECT * FROM projetos'), 'Backlog geral (todos os projetos)')]), ['admin' => true]);
            return;

        case 'sugestoes':
            exigir_papel('pedido.ver');
            echo layout('Kanban geral', vista_painel('backlog', ['form' => vista_kanban(
                kanban_montar(null), consulta('SELECT * FROM projetos'), 'Backlog geral (todos os projetos)')]), ['admin' => true]);
            return;

        default:
            nao_encontrado('Rota do painel inexistente.');
    }
}

/* ------------------------------------------------------------- gravações */

function salvar_projeto(array $d): string
{
    $id = (int) ($d['id'] ?? 0);
    if (!csrf_valido($d['csrf'] ?? ($_POST['csrf'] ?? null))) {
        return 'Sessão expirada: nada foi salvo.';
    }
    if ($id > 0 && !pode('projeto.editar')) {
        return 'Sem permissão para editar.';
    }
    if ($id === 0 && !pode('projeto.criar')) {
        return 'Sem permissão para criar.';
    }
    $titulo = trim((string) ($d['titulo'] ?? ''));
    if ($titulo === '') {
        return 'O título é obrigatório.';
    }
    $slug = slugificar((string) ($d['slug'] ?? ''));
    if (trim((string) ($d['slug'] ?? '')) === '') {
        $slug = slugificar($titulo);
        $n = 2;
        while (um('SELECT id FROM projetos WHERE slug = :s AND id <> :i', [':s' => $slug, ':i' => $id])) {
            $slug = slugificar($titulo) . '-' . $n++;
        }
    } else {
        $colide = um('SELECT id FROM projetos WHERE slug = :s AND id <> :i', [':s' => $slug, ':i' => $id]);
        if ($colide) {
            return 'Já existe um projeto com esse slug.';
        }
    }
    $nivel = array_key_exists((string) ($d['nivel_divulgacao'] ?? ''), IN3_NIVEIS) ? (string) $d['nivel_divulgacao'] : 'roadmap';
    $publicar = !empty($d['mostrar_ao_publico']) ? 1 : 0;
    if ($publicar === 1 && !pode('projeto.publicar')) {
        $publicar = 0;
    }
    $campos = [
        ':slug' => $slug, ':t' => $titulo, ':e' => (string) ($d['emoji'] ?? '🧊'),
        ':i' => slugificar((string) ($d['icone'] ?? $d['categoria'] ?? 'governanca')),
        ':c' => (string) ($d['categoria'] ?? 'Governança & Infra'), ':s' => (string) ($d['status'] ?? 'em incubação'),
        ':r' => limpar_texto((string) ($d['resumo'] ?? ''), 320), ':co' => (string) ($d['cor'] ?? '#2A9581'),
        ':o' => (int) ($d['ordem'] ?? 100), ':p' => $publicar,
        ':l' => !empty($d['mostrar_link_repo']) ? 1 : 0, ':n' => $nivel, ':q' => agora(),
    ];
    if ($id > 0) {
        executar('UPDATE projetos SET slug=:slug, titulo=:t, emoji=:e, icone=:i, categoria=:c, status=:s,
                  resumo=:r, cor=:co, ordem=:o, mostrar_ao_publico=:p, mostrar_link_repo=:l,
                  nivel_divulgacao=:n, atualizado_em=:q WHERE id=' . $id, $campos);
    } else {
        $campos[':q2'] = agora();
        executar('INSERT INTO projetos (slug,titulo,emoji,icone,categoria,status,resumo,cor,ordem,
                  mostrar_ao_publico,mostrar_link_repo,nivel_divulgacao,criado_em,atualizado_em)
                  VALUES (:slug,:t,:e,:i,:c,:s,:r,:co,:o,:p,:l,:n,:q2,:q)', $campos);
        $id = (int) banco()->lastInsertId();
    }
    // repositórios vinculados
    executar('DELETE FROM projeto_repos WHERE projeto_id = :p', [':p' => $id]);
    foreach ((array) ($d['repos'] ?? []) as $rid) {
        executar('INSERT OR IGNORE INTO projeto_repos (projeto_id, repo_id) VALUES (:p,:r)',
            [':p' => $id, ':r' => (int) $rid]);
    }
    // camadas
    for ($i = 1; $i <= 4; $i++) {
        $tit = trim((string) ($d['camada_' . $i . '_titulo'] ?? ''));
        $corpo = trim((string) ($d['camada_' . $i . '_corpo'] ?? ''));
        executar('INSERT INTO camadas (projeto_id, profundidade, titulo, corpo, atualizado_em)
                  VALUES (:p,:n,:t,:c,:q)
                  ON CONFLICT (projeto_id, profundidade) DO UPDATE SET titulo = excluded.titulo,
                    corpo = excluded.corpo, atualizado_em = excluded.atualizado_em', [
            ':p' => $id, ':n' => $i,
            ':t' => $tit !== '' ? $tit : (IN3_NIVEL_ROTULO[$i] ?? 'Camada'),
            ':c' => $corpo, ':q' => agora(),
        ]);
    }
    auditar($id > 0 ? 'projeto_salvo' : 'projeto_criado', 'projeto#' . $id, 'slug=' . $slug . ' publico=' . $publicar);
    return 'Projeto salvo. Camadas e vínculos atualizados.';
}

function salvar_usuario(array $d, ?array $alvo): string
{
    if (!csrf_valido($d['csrf'] ?? null)) {
        return 'Sessão expirada: nada foi salvo.';
    }
    $id = (int) ($d['id'] ?? 0);
    if ($id > 0 && !pode('usuario.editar')) {
        return 'Sem permissão para editar usuários.';
    }
    if ($id === 0 && !pode('usuario.criar')) {
        return 'Sem permissão para criar usuários.';
    }
    $nome = trim((string) ($d['usuario'] ?? ''));
    if ($nome === '') {
        return 'Informe o nome de usuário.';
    }
    $papel = array_key_exists((string) ($d['papel'] ?? ''), IN3_PAPEIS) ? (string) $d['papel'] : 'leitor';
    if ($papel === 'root' && !pode('*') && (usuario_atual()['papel'] ?? '') !== 'root') {
        return 'Somente um root pode criar outro root.';
    }
    $clareza = max(1, min(4, (int) ($d['clareza'] ?? 1)));
    $senha = (string) ($d['senha'] ?? '');
    $troca = !empty($d['exigir_troca']) ? 1 : 0;

    if ($id > 0) {
        $alvo = $alvo ?? um('SELECT * FROM usuarios WHERE id = :i', [':i' => $id]);
        if (!$alvo) {
            return 'Usuário inexistente.';
        }
        if ((string) $alvo['papel'] === 'root' && (usuario_atual()['papel'] ?? '') !== 'root') {
            return 'Somente um root pode alterar outro root.';
        }
        executar('UPDATE usuarios SET usuario=:u, nome=:n, email=:e, papel=:p, clareza=:c, ativo=:a,
                  precisa_trocar_senha = CASE WHEN :t = 1 THEN 1 ELSE precisa_trocar_senha END
                  WHERE id=:i', [
            ':u' => $nome, ':n' => limpar_texto((string) ($d['nome'] ?? ''), 120),
            ':e' => limpar_texto((string) ($d['email'] ?? ''), 160), ':p' => $papel,
            ':c' => $clareza, ':a' => !empty($d['ativo']) ? 1 : 0, ':t' => $troca, ':i' => $id,
        ]);
        if ($senha !== '') {
            if ($erro = senha_forte($senha)) {
                return $erro;
            }
            definer_senha_id($id, $senha);
        }
        auditar('usuario_editado', 'usuario#' . $id, 'papel=' . $papel . ' clareza=' . $clareza);
        return 'Usuário atualizado.';
    }

    if ($erro = senha_forte($senha)) {
        return $erro;
    }
    if (um('SELECT id FROM usuarios WHERE usuario = :u', [':u' => $nome])) {
        return 'Esse nome de usuário já existe.';
    }
    criar_usuario($nome, $senha, $papel, $clareza, limpar_texto((string) ($d['nome'] ?? ''), 120),
        limpar_texto((string) ($d['email'] ?? ''), 160), $troca === 1);
    auditar('usuario_criado', $nome, 'papel=' . $papel . ' clareza=' . $clareza);
    return 'Usuário criado.';
}

function definer_senha_id(int $id, string $senha): void
{
    executar('UPDATE usuarios SET hash = :h, precisa_trocar_senha = 0 WHERE id = :i', [
        ':h' => password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]), ':i' => $id,
    ]);
}

function redirecionar(string $para): void
{
    header('Location: ' . $para, true, 302);
    exit;
}

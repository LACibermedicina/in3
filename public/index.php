<?php
declare(strict_types=1);
/**
 * IN³ · front controller.
 * Site público na raiz; painel em rota apartada (IN3_ROTA_PAINEL), nunca
 * referenciada em link, sitemap ou robots do site público.
 */

require_once dirname(__DIR__) . '/src/nucleo.php';

/* --------------------------- uso pela linha de comando ------------------ */
if (PHP_SAPI === 'cli') {
    require IN3_RAIZ . '/src/instalador.php';
    if (in_array('--instalar', $argv, true)) {
        instalador_cli($argv);
        exit(0);
    }
    fwrite(STDERR, "Uso: php public/index.php --instalar [--usuario=NOME --senha=SENHA]\n");
    exit(1);
}

$caminho = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$caminho = '/' . trim($caminho, '/');
$caminho = $caminho === '/' ? '/' : rtrim($caminho, '/');

/* ---- arquivos estáticos (quando o servidor embutido do PHP é usado) ---- */
if (preg_match('#^/assets/(.+)$#', $caminho, $mm) && !str_contains($mm[1], '..')) {
    $arq = IN3_PUBLICO . '/assets/' . $mm[1];
    if (is_file($arq)) {
        $ext = strtolower((string) pathinfo($arq, PATHINFO_EXTENSION));
        $tipos = ['css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml',
            'png' => 'image/png', 'webp' => 'image/webp', 'woff2' => 'font/woff2', 'json' => 'application/json'];
        header('Content-Type: ' . ($tipos[$ext] ?? 'application/octet-stream') . '; charset=UTF-8');
        header('Cache-Control: public, max-age=86400');
        readfile($arq);
        exit;
    }
    nao_encontrado('Arquivo estático inexistente');
}

/* ------------------------------- API ------------------------------------ */
if (str_starts_with($caminho, '/api/')) {
    if (!banco_pronto()) {
        json_saida(['erro' => 'instalação pendente', 'instalador' => url('/instalar')], 503);
    }
    api_rota($caminho);
}

/* --------------------- banco: 1ª execução / instalador ------------------ */
if (!banco_pronto() || !existe_admin()) {
    if ($caminho === '/instalar' || $caminho === '/instalar/'.'passo2') {
        require IN3_RAIZ . '/src/instalador.php';
        instalador_rota($caminho);
    }
    cabecalhos_seguranca(false);
    echo layout_painel('Instalação', view('painel_instalar_intro.php', [
        'urlInstalar' => url('/instalar'),
        'motivo' => !banco_pronto() ? 'O banco de dados ainda não existe.' : 'Nenhum administrador foi criado.',
    ]), ['ativo' => 'sistema']);
    exit;
}

/* ------------------------- verificação de saúde ------------------------- */
if ($caminho === '/saude') {
    json_saida([
        'ok' => true,
        'versao' => configuracao('esquema_versao', '0'),
        'fts5' => fts_ligado() ? 'ligado' : 'desligado',
        'projetos' => (int) escalar('SELECT count(*) FROM projetos'),
        'repos' => (int) escalar('SELECT count(*) FROM repos'),
        'sincronizado_em' => configuracao('sincronizado_em', ''),
        'php' => PHP_VERSION,
    ]);
}

$painel = rota_painel();

/* ------------------------------- painel --------------------------------- */
if ($caminho === $painel || str_starts_with($caminho, $painel . '/')) {
    $sub = trim(substr($caminho, strlen($painel)), '/');
    cabecalhos_seguranca(false);

    if ($sub === 'entrar' || $sub === '') {
        if (metodo() === 'POST') {
            csrf_verifica();
            $r = autenticar(entrada('usuario'), (string) ($_POST['senha'] ?? ''));
            if ($r['ok']) {
                flash('ok', 'Bem-vindo, ' . ($r['usuario']['nome'] !== '' ? $r['usuario']['nome'] : $r['usuario']['usuario']) . '.');
                redirecionar(url_painel('/painel'));
            }
            flash('erro', (string) $r['erro']);
            redirecionar(url_painel('/entrar'));
        }
        if (usuario_atual()) {
            redirecionar(url_painel('/painel'));
        }
        echo layout_painel('Entrar', view('painel_entrar.php', ['csrf' => csrf_campo(), 'rota' => url_painel('/entrar')]), ['ativo' => '']);
        exit;
    }

    if ($sub === 'sair') {
        encerrar_sessao();
        redirecionar(url_painel('/entrar'));
    }

    $u = usuario_atual();
    if ($u === null) {
        redirecionar(url_painel('/entrar'));
    }

    $clareza = clareza_visitante();
    $corpo = '';

    if ($sub === 'painel') {
        $corpo = view('painel_inicio.php', painel_dados_inicio());
    } elseif ($sub === 'projetos') {
        $corpo = view('painel_projetos.php', painel_dados_projetos());
    } elseif (preg_match('#^projeto/(\d+)$#', $sub, $mm)) {
        $corpo = view('painel_projeto_form.php', painel_dados_form((int) $mm[1]));
    } elseif ($sub === 'repos') {
        $corpo = view('painel_repos.php', painel_dados_repos());
    } elseif ($sub === 'pedidos') {
        if (metodo() === 'POST') {
            csrf_verifica();
            painel_acao_pedido();
            redirecionar(url_painel('/pedidos'));
        }
        $corpo = view('painel_pedidos.php', painel_dados_pedidos());
    } elseif ($sub === 'busca') {
        $termo = entrada('q');
        $r = $termo !== '' ? busca($termo, $clareza, true, ['categoria' => entrada('categoria')]) : null;
        if ($termo !== '') {
            registrar_acesso('painel.busca', $termo, (int) $r['total']);
        }
        $corpo = view('painel_busca.php', ['resultado' => $r, 'termo' => $termo, 'categorias' => categorias_publicas(true)]);
    } elseif ($sub === 'usuarios' && eh_admin()) {
        if (metodo() === 'POST') {
            csrf_verifica();
            painel_acao_usuario();
            redirecionar(url_painel('/usuarios'));
        }
        $corpo = view('painel_usuarios.php', painel_dados_usuarios());
    } elseif ($sub === 'sistema' && eh_admin()) {
        if (metodo() === 'POST') {
            csrf_verifica();
            painel_acao_sistema();
            redirecionar(url_painel('/sistema'));
        }
        $corpo = view('painel_sistema.php', painel_dados_sistema());
    } else {
        nao_encontrado('Rota do painel inexistente');
    }

    $ativo = preg_replace('#/.*$#', '', $sub) ?: 'painel';
    echo layout_painel('Painel', $corpo, ['ativo' => $ativo === 'projeto' ? 'projetos' : $ativo]);
    exit;
}

/* ---------------------------- site público ------------------------------ */
cabecalhos_seguranca(true);
$clareza = clareza_visitante();
$admin = false;

/* /p/{slug} — hotsite do projeto */
if (preg_match('#^/p/([a-z0-9\-]+)$#', $caminho, $mm)) {
    $p = projeto_por_slug($mm[1]);
    if (!$p || (int) $p['mostrar_ao_publico'] !== 1) {
        nao_encontrado('Projeto não publicado');
    }
    $p['_repos'] = projeto_repos((int) $p['id']);
    $p['_tech'] = projeto_tech((int) $p['id']);
    $p['_marcos'] = projeto_marcos((int) $p['id']);
    $montagem = secoes_projeto($p, $clareza);
    registrar_acesso('publico.projeto', (string) $p['slug'], 1);
    echo layout_publico(
        $p['titulo'] . ' · IN³',
        campo_publicavel((string) $p['resumo']) ? texto_limpo((string) $p['resumo'], 160) : 'Projeto da incubadora IN³ (m3d.pro).',
        view('publico_projeto.php', [
            'p' => $p,
            'montagem' => $montagem,
            'clareza' => $clareza,
            'proximo' => proximo_nivel_bloqueado($montagem['nivel_projeto'], $clareza),
            'csrf' => csrf_campo(),
            'voxel' => voxel_projeto($p),
            'relacionados' => array_slice(array_values(array_filter(
                projetos_visiveis($clareza),
                static fn($x) => $x['categoria'] === $p['categoria'] && $x['id'] !== $p['id']
            )), 0, 3),
        ]),
        ['ativo' => 'inicio', 'clareza' => $clareza, 'dados' => [],
         'json' => ['tipo' => 'projeto', 'voxel' => cena_voxel([$p])]]
    );
    exit;
}

/* /busca */
if ($caminho === '/busca') {
    $termo = entrada('q');
    $filtro = ['categoria' => entrada('categoria')];
    $r = $termo !== '' ? busca($termo, $clareza, false, $filtro) : null;
    if ($termo !== '') {
        registrar_acesso('publico.busca', $termo, (int) $r['total']);
    }
    echo layout_publico(
        'Busca · IN³',
        'Busca no portfólio vivo da IN³, respeitando o nível de liberação de cada projeto.',
        view('publico_busca.php', [
            'resultado' => $r, 'termo' => $termo, 'clareza' => $clareza,
            'categorias' => categorias_publicas(), 'filtro' => $filtro,
        ]),
        ['ativo' => 'busca', 'clareza' => $clareza, 'json' => ['tipo' => 'busca']]
    );
    exit;
}

/* /tecnologias */
if ($caminho === '/tecnologias') {
    $t = api_tecnologias($clareza, false)['tecnologias'];
    echo layout_publico(
        'Tecnologias · IN³',
        'Tecnologias presentes nos projetos da incubadora IN³.',
        view('publico_tecnologias.php', ['tecnologias' => $t, 'clareza' => $clareza]),
        ['ativo' => 'tecnologias', 'clareza' => $clareza]
    );
    exit;
}

/* /pedido */
if ($caminho === '/pedido') {
    if (metodo() === 'POST') {
        csrf_verifica();
        $r = registrar_pedido($_POST);
        if ($r['ok']) {
            flash('ok', 'Pedido registrado. A equipe da IN³ responde no contato informado.');
            redirecionar(url('/pedido'));
        }
        $erros = $r['erros'];
    }
    $projetos = projetos_visiveis($clareza);
    echo layout_publico(
        'Pedir detalhamento · IN³',
        'Peça acesso ao detalhamento institucional, técnico ou ao playbook completo de um projeto da IN³.',
        view('publico_pedido.php', [
            'projetos' => $projetos, 'csrf' => csrf_campo(), 'erros' => $erros ?? [],
            'clareza' => $clareza,
        ]),
        ['ativo' => 'pedido', 'clareza' => $clareza]
    );
    exit;
}

/* /acesso/{token} — liberação temporária de detalhamento */
if (preg_match('#^/acesso/([a-f0-9]{16,64})$#', $caminho, $mm)) {
    $a = usar_acesso($mm[1]);
    if ($a) {
        flash('ok', 'Acesso liberado até ' . data_br((string) $a['expira_em'], true) . '.');
        $alvo = $a['projeto_id'] ? projeto_por_id((int) $a['projeto_id']) : null;
        redirecionar($alvo ? url('/p/' . $alvo['slug']) : url('/'));
    }
    flash('erro', 'Link de acesso inválido, expirado ou já utilizado além do limite.');
    redirecionar(url('/pedido'));
}

/* /sitemap.xml e /robots.txt */
if ($caminho === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    $x = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    $base = (cfg('IN3_URL_BASE', 'https://in.m3d.pro') ?? '');
    foreach (projetos_visiveis(4) as $p) {
        $x .= '<url><loc>' . e($base . '/p/' . $p['slug']) . '</loc><lastmod>'
            . e(substr((string) $p['atualizado_em'], 0, 10)) . '</lastmod></url>';
    }
    echo $x . '</urlset>';
    exit;
}
if ($caminho === '/robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nAllow: /\nDisallow: /api/\nDisallow: /busca?\nDisallow: /instalar\n\nSitemap: "
        . (cfg('IN3_URL_BASE', 'https://in.m3d.pro')) . "/sitemap.xml\n";
    exit;
}

/* raiz — portfólio */
if ($caminho === '/') {
    $projetos = projetos_visiveis($clareza);
    registrar_acesso('publico.inicio', '', count($projetos));
    $cob = cobertura_repos();
    $visPub = 0;
    foreach ($cob['visibilidade'] as $v) {
        if ($v['visibilidade'] === 'publico') {
            $visPub = (int) $v['n'];
        }
    }
    $stats = [
        'projetos' => count($projetos),
        'repos' => $cob['total'],
        'repos_publicos' => $visPub,
        'categorias' => count(categorias_publicas()),
        'atualizado' => (string) configuracao('sincronizado_em', ''),
    ];
    echo layout_publico(
        'IN³ · in.m3d.pro — portfólio vivo da incubadora',
        'Portfólio vivo da incubadora m3d.pro: pesquisa, engenharia e saúde digital, com detalhamento liberado por nível.',
        view('publico_inicio.php', [
            'projetos' => $projetos, 'clareza' => $clareza, 'stats' => $stats,
            'categorias' => categorias_publicas(), 'status' => status_disponiveis(),
            'manifesto' => (string) configuracao('manifesto', ''),
        ]),
        ['ativo' => 'inicio', 'clareza' => $clareza,
         'dados' => ['brand' => ['slogan' => (string) configuracao('slogan', 'O que entra na m3d, sai ao cubo.')]],
         'json' => ['tipo' => 'inicio', 'voxel' => cena_voxel($projetos)]]
    );
    exit;
}

nao_encontrado('Página não encontrada');

/* =====================================================================
 * Rotinas de painel (ações de formulário) — mantidas aqui para que o
 * roteador permaneça legível.
 * =================================================================== */

function painel_acao_pedido(): void
{
    $id = (int) entrada('id');
    $acao = entrada('acao');
    if ($id <= 0) {
        return;
    }
    if ($acao === 'liberar') {
        $dias = max(1, min(365, (int) entrada('dias', '30')));
        $clareza = max(2, min(4, (int) entrada('clareza', '3')));
        $pedido = um('SELECT * FROM pedidos WHERE id = :i', [':i' => $id]);
        if (!$pedido) {
            flash('erro', 'Pedido não encontrado.');
            return;
        }
        $projeto = um('SELECT * FROM projetos WHERE titulo LIKE :t OR slug LIKE :s LIMIT 1', [
            ':t' => '%' . $pedido['projeto'] . '%', ':s' => '%' . slugificar((string) $pedido['projeto']) . '%',
        ]);
        $projetoId = $projeto ? (int) $projeto['id'] : null;
        $token = emitir_acesso((int) ($projetoId ?? 0), $clareza, $id, $dias);
        executa("UPDATE pedidos SET estado = 'liberado', respondido_em = :q WHERE id = :i", [':q' => agora(), ':i' => $id]);
        flash('ok', 'Acesso emitido por ' . $dias . ' dia(s) no nível "' . nivel_rotulo($clareza) . '". Link: '
            . (cfg('IN3_URL_BASE', '') ?? '') . '/acesso/' . $token . ' — copie e envie ao solicitante.');
        return;
    }
    $estado = in_array($acao, ['recusado', 'arquivado', 'novo'], true) ? $acao : 'arquivado';
    executa('UPDATE pedidos SET estado = :e, respondido_em = :q WHERE id = :i', [':e' => $estado, ':q' => agora(), ':i' => $id]);
    auditar('pedido_estado', 'pedidos', $id, $estado);
    flash('ok', 'Pedido marcado como ' . $estado . '.');
}

function painel_acao_usuario(): void
{
    $acao = entrada('acao');
    if ($acao === 'criar') {
        try {
            criar_usuario(
                entrada('usuario'), (string) ($_POST['senha'] ?? ''), entrada('papel', 'leitor'),
                (int) entrada('clareza', '1'), entrada('nome'), entrada('email')
            );
            flash('ok', 'Usuário criado. A senha não é exibida nem registrada em log.');
        } catch (Throwable $e) {
            flash('erro', $e->getMessage());
        }
        return;
    }
    $id = (int) entrada('id');
    if ($id <= 0 || $id === (int) (usuario_atual()['id'] ?? 0) && $acao === 'desativar') {
        flash('erro', 'Ação recusada para o próprio usuário.');
        return;
    }
    if ($acao === 'desativar') {
        executa('UPDATE usuarios SET ativo = 0 WHERE id = :i', [':i' => $id]);
        executa('DELETE FROM sessoes WHERE usuario_id = :i', [':i' => $id]);
        auditar('usuario_desativado', 'usuarios', $id);
        flash('ok', 'Usuário desativado e sessões encerradas.');
    } elseif ($acao === 'ativar') {
        executa('UPDATE usuarios SET ativo = 1 WHERE id = :i', [':i' => $id]);
        auditar('usuario_ativado', 'usuarios', $id);
        flash('ok', 'Usuário reativado.');
    } elseif ($acao === 'papel') {
        $papel = entrada('papel', 'leitor');
        if (in_array($papel, ['admin', 'curador', 'leitor'], true)) {
            executa('UPDATE usuarios SET papel = :p WHERE id = :i', [':p' => $papel, ':i' => $id]);
            auditar('usuario_papel', 'usuarios', $id, $papel);
            flash('ok', 'Papel atualizado.');
        }
    } elseif ($acao === 'clareza') {
        executa('UPDATE usuarios SET clareza = :c WHERE id = :i', [':c' => max(1, min(4, (int) entrada('clareza', '1'))), ':i' => $id]);
        flash('ok', 'Clareza atualizada.');
    } elseif ($acao === 'senha') {
        $s = (string) ($_POST['senha'] ?? '');
        if (mb_strlen($s) < 10) {
            flash('erro', 'A senha deve ter ao menos 10 caracteres.');
            return;
        }
        executa('UPDATE usuarios SET senha_hash = :h WHERE id = :i', [':h' => password_hash($s, PASSWORD_DEFAULT), ':i' => $id]);
        executa('DELETE FROM sessoes WHERE usuario_id = :i', [':i' => $id]);
        auditar('usuario_senha_redefinida', 'usuarios', $id);
        flash('ok', 'Senha redefinida. Sessões do usuário encerradas.');
    }
}

function painel_acao_sistema(): void
{
    $acao = entrada('acao');
    if ($acao === 'reindexar') {
        $n = reindexar_tudo();
        flash('ok', 'Busca reconstruída para ' . $n . ' projeto(s).');
        return;
    }
    if ($acao === 'sincronizar') {
        $token = (string) ($_POST['token'] ?? '');
        $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg(IN3_RAIZ . '/tools/sincronizar.php');
        $cmd .= ' --conta ' . escapeshellarg((string) configuracao('conta_github', (string) cfg('IN3_CONTA', 'LACibermedica')));
        if ($token !== '') {
            putenv('GITHUB_TOKEN=' . $token);
            $descritores = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = proc_open($cmd, $descritores, $tubos);
            $saida = $proc ? stream_get_contents($tubos[1]) : '';
            if ($proc) {
                proc_close($proc);
            }
            flash('ok', 'Sincronização executada (token usado apenas nesta chamada, nunca gravado).');
            auditar('sincronizacao', 'repos', null, texto_limpo($saida, 300));
            return;
        }
        flash('erro', 'Informe um token de leitura do GitHub para sincronizar repositórios privados.');
        return;
    }
    if ($acao === 'renovar_sessao') {
        $seg = max(900, min(86400, (int) entrada('segundos', '7200')));
        configurar('sessao_segundos', (string) $seg);
        flash('ok', 'Duração de sessão atualizada.');
        return;
    }
    if ($acao === 'politica') {
        $nivel = in_array(entrada('nivel_padrao'), array_keys(IN3_NIVEIS), true) ? entrada('nivel_padrao') : 'institucional_roadmap';
        configurar('nivel_padrao', $nivel);
        flash('ok', 'Nível padrão de novos projetos atualizado para "' . nivel_rotulo(nivel_valor($nivel)) . '".');
        return;
    }
}

/* ------------------------------------------------- dados para as views */

function painel_dados_inicio(): array
{
    $clareza = clareza_visitante();
    $projetos = projetos_visiveis($clareza, true);
    $cob = cobertura_repos();
    $pub = 0;
    foreach ($projetos as $p) {
        $pub += (int) $p['mostrar_ao_publico'];
    }
    $porNivel = [];
    foreach (array_keys(IN3_NIVEIS) as $n) {
        $porNivel[$n] = (int) escalar('SELECT count(*) FROM projetos WHERE nivel_divulgacao = :n', [':n' => $n]);
    }
    return [
        'projetos' => $projetos,
        'clareza' => $clareza,
        'cobertura' => $cob,
        'total' => count($projetos),
        'publicos' => $pub,
        'internos' => count($projetos) - $pub,
        'por_nivel' => $porNivel,
        'pedidos_novos' => (int) escalar("SELECT count(*) FROM pedidos WHERE estado = 'novo'"),
        'pedidos_total' => (int) escalar('SELECT count(*) FROM pedidos'),
        'acessos_ativos' => (int) escalar("SELECT count(*) FROM acessos WHERE estado = 'ativo' AND expira_em > :q", [':q' => agora()]),
        'log' => consulta('SELECT * FROM acesso_log ORDER BY quando DESC LIMIT 12'),
        'auditoria' => consulta('SELECT * FROM auditoria ORDER BY quando DESC LIMIT 10'),
        'fonte_repos' => (string) configuracao('fonte_repos', 'não sincronizado'),
        'sincronizado_em' => (string) configuracao('sincronizado_em', ''),
        'fts' => fts_ligado() ? 'ligado' : 'desligado',
        'versao' => (string) configuracao('esquema_versao', IN3_VERSAO),
    ];
}

function painel_dados_projetos(): array
{
    $clareza = clareza_visitante();
    return [
        'projetos' => projetos_visiveis($clareza, true),
        'categorias' => categorias_publicas(true),
        'status' => status_disponiveis(true),
        'csrf' => csrf_campo(),
    ];
}

function painel_dados_form(int $id): array
{
    $p = $id > 0 ? projeto_por_id($id) : null;
    if ($id > 0 && !$p) {
        nao_encontrado('Projeto inexistente');
    }
    $repos = consulta('SELECT id, nome, visibilidade, linguagem, fork FROM repos ORDER BY nome');
    $ids = $p ? array_map(static fn($r) => (int) $r['id'], projeto_repos($id)) : [];
    $marcos = $p ? projeto_marcos($id) : [];
    $linhas = [];
    foreach ($marcos as $m) {
        $linhas[] = (string) $m['data'] . ' | ' . (string) $m['marco'];
    }
    return [
        'p' => $p ?? [
            'id' => 0, 'slug' => '', 'titulo' => '', 'emoji' => '🧊', 'icone' => 'cubo', 'categoria' => '',
            'status' => 'em incubação', 'mostrar_ao_publico' => 0, 'mostrar_link_repo' => 0,
            'nivel_divulgacao' => (string) configuracao('nivel_padrao', 'institucional_roadmap'),
            'resumo' => '', 'publico_alvo' => '', 'problema' => '', 'solucao' => '', 'como_funciona' => '',
            'validacao' => '', 'arquitetura' => '', 'integracoes' => '', 'privacidade' => '', 'riscos' => '',
            'notas_internas' => '', 'playbook_ref' => '', 'ordem' => 100,
        ],
        'repos' => $repos,
        'repos_do_projeto' => $ids,
        'marcos_texto' => implode("\n", $linhas),
        'tecnologias' => $p ? implode(', ', projeto_tech($id)) : '',
        'csrf' => csrf_campo(),
        'icones' => ['cubo', 'telemedicina', 'fiscal', 'catalogo', 'educacao', 'sensor', 'esporte', 'vitrine', 'ia', 'cirurgia', 'certificado', 'governanca'],
        'url_salvar' => url_painel('/projeto/' . $id),
    ];
}

function painel_dados_repos(): array
{
    return [
        'repos' => consulta(
            'SELECT r.*, (SELECT count(*) FROM projeto_repos pr WHERE pr.repo_id = r.id) AS n_projetos
               FROM repos r ORDER BY r.visibilidade ASC, r.gh_atualizado_em DESC'
        ),
        'cobertura' => cobertura_repos(),
        'fonte' => (string) configuracao('fonte_repos', 'não sincronizado'),
        'sincronizado_em' => (string) configuracao('sincronizado_em', ''),
        'readmes' => (int) escalar('SELECT count(*) FROM repos WHERE gh_commit_msg != ""'),
    ];
}

function painel_dados_pedidos(): array
{
    return ['pedidos' => pedidos(), 'csrf' => csrf_campo(), 'acessos' => consulta('SELECT * FROM acessos ORDER BY criado_em DESC LIMIT 40')];
}

function painel_dados_usuarios(): array
{
    return [
        'usuarios' => consulta('SELECT id, usuario, nome, email, papel, clareza, ativo, criado_em, ultimo_acesso FROM usuarios ORDER BY papel, usuario'),
        'sessoes' => consulta('SELECT s.criada_em, s.expira_em, s.ip, u.usuario FROM sessoes s LEFT JOIN usuarios u ON u.id = s.usuario_id ORDER BY s.criada_em DESC LIMIT 20'),
        'csrf' => csrf_campo(),
        'niveis' => IN3_NIVEL_ROTULO,
    ];
}

function painel_dados_sistema(): array
{
    $arqBanco = (string) cfg('IN3_BANCO', IN3_DADOS . '/in3.db');
    return [
        'csrf' => csrf_campo(),
        'php' => PHP_VERSION,
        'sqlite' => (string) escalar('SELECT sqlite_version()'),
        'fts' => fts_ligado() ? 'ligado' : 'desligado',
        'versao' => (string) configuracao('esquema_versao', IN3_VERSAO),
        'banco' => $arqBanco,
        'banco_mb' => is_file($arqBanco) ? round(filesize($arqBanco) / 1048576, 2) : 0,
        'tabelas' => consulta("SELECT name, (SELECT count(*) FROM pragma_table_info(name)) AS colunas FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"),
        'contagens' => [
            'projetos' => (int) escalar('SELECT count(*) FROM projetos'),
            'repos' => (int) escalar('SELECT count(*) FROM repos'),
            'pedidos' => (int) escalar('SELECT count(*) FROM pedidos'),
            'acessos' => (int) escalar('SELECT count(*) FROM acessos'),
            'usuarios' => (int) escalar('SELECT count(*) FROM usuarios'),
            'busca_linhas' => (int) escalar('SELECT count(*) FROM busca_texto'),
            'log' => (int) escalar('SELECT count(*) FROM acesso_log'),
        ],
        'auditoria' => consulta('SELECT * FROM auditoria ORDER BY quando DESC LIMIT 30'),
        'rota_painel' => rota_painel(),
        'conta_github' => (string) configuracao('conta_github', (string) cfg('IN3_CONTA', '')),
        'fonte_repos' => (string) configuracao('fonte_repos', 'não sincronizado'),
        'sincronizado_em' => (string) configuracao('sincronizado_em', ''),
        'nivel_padrao' => nivel_rotulo(nivel_valor((string) configuracao('nivel_padrao', 'institucional_roadmap'))),
    ];
}

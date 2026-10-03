<?php
declare(strict_types=1);
/**
 * =====================================================================
 * IN³ · INcubator — núcleo do sistema
 * Config · banco SQLite · autenticação · RBAC · acervo · busca · ícones
 * PHP 8 + PDO/SQLite. Sem dependências externas.
 * =====================================================================
 */

const IN3_VERSAO = '3.0.0';
define('IN3_RAIZ', dirname(__DIR__));
define('IN3_DATA', IN3_RAIZ . '/data');
define('IN3_BANCO', IN3_DATA . '/in3.db');

/* ------------------------------------------------------------- catálogos */

const IN3_NIVEIS = ['roadmap' => 1, 'institucional' => 2, 'tecnico' => 3, 'playbook' => 4];

const IN3_NIVEL_ROTULO = [
    1 => 'Roadmap institucional',
    2 => 'Institucional',
    3 => 'Técnico',
    4 => 'Playbook completo',
];

const IN3_NIVEL_DESCRICAO = [
    1 => 'Visão de futuro e objetivo do projeto, sem detalhe técnico.',
    2 => 'Problema, solução, público-alvo e situação atual.',
    3 => 'Arquitetura, integrações, dados tratados e critérios de validação.',
    4 => 'Ficha completa: riscos, privacidade e LGPD, validações, notas internas.',
];

const IN3_PAPEIS = ['root', 'admin', 'curador', 'leitor'];

const IN3_PAPEL_ROTULO = [
    'root'    => 'Root',
    'admin'   => 'Administrador',
    'curador' => 'Curador',
    'leitor'  => 'Leitor',
];

/** Matriz de permissões. Checada SEMPRE no servidor — nunca só na tela. */
const IN3_PERMISSOES = [
    'root' => ['*'],
    'admin' => [
        'projeto.ver', 'projeto.criar', 'projeto.editar', 'projeto.publicar', 'projeto.excluir',
        'camada.editar', 'pedido.ver', 'pedido.liberar', 'usuario.ver', 'usuario.criar',
        'usuario.editar', 'sistema.ver', 'sistema.sincronizar', 'busca.ampla',
    ],
    'curador' => [
        'projeto.ver', 'projeto.criar', 'projeto.editar', 'camada.editar',
        'pedido.ver', 'sistema.ver', 'busca.ampla',
    ],
    'leitor' => ['projeto.ver', 'busca.propria'],
];

/* -------------------------------------------------------------- ambiente */

function in3_env(): array
{
    static $env = null;
    if (is_array($env)) {
        return $env;
    }
    $env = [];
    $arq = IN3_RAIZ . '/.env';
    if (is_file($arq)) {
        foreach (file($arq, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
            $l = trim($l);
            if ($l === '' || $l[0] === '#' || !str_contains($l, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $l, 2);
            $env[trim($k)] = trim(trim($v), "\"'");
        }
    }
    return $env;
}

function cfg(string $chave, string $padrao = ''): string
{
    $e = in3_env();
    if (isset($e[$chave]) && $e[$chave] !== '') {
        return (string) $e[$chave];
    }
    $g = getenv($chave);
    return ($g === false || $g === '') ? $padrao : (string) $g;
}

function rota_painel(): string
{
    $r = trim(cfg('IN3_ROTA_PAINEL', 'console'), '/');
    return $r === '' ? 'console' : $r;
}

function url(string $caminho = '/'): string
{
    return '/' . ltrim($caminho, '/');
}

function url_painel(string $sufixo = ''): string
{
    return url(rota_painel() . $sufixo);
}

function e(?string $t): string
{
    return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
}

function agora(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}

function data_br(?string $iso): string
{
    $iso = (string) $iso;
    if ($iso === '') {
        return '—';
    }
    $t = strtotime(str_replace('T', ' ', substr($iso, 0, 19)));
    return $t === false ? e($iso) : date('d/m/Y', $t);
}

function slugificar(string $t): string
{
    $t = mb_strtolower(trim($t), 'UTF-8');
    $mapa = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','í'=>'i','ì'=>'i','î'=>'i',
             'ó'=>'o','ô'=>'o','õ'=>'o','ò'=>'o','ú'=>'u','ù'=>'u','û'=>'u','ç'=>'c','ñ'=>'n'];
    $t = strtr($t, $mapa);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t) ?? '';
    return trim((string) $t, '-') ?: 'item';
}

function limpar_texto(string $t, int $max = 240): string
{
    $t = trim(preg_replace('/\s+/u', ' ', strip_tags($t)) ?? '');
    return mb_strlen($t) > $max ? mb_substr($t, 0, $max - 1) . '…' : $t;
}

/* ----------------------------------------------------------------- banco */

function banco_existe(): bool
{
    return is_file(IN3_BANCO) && filesize(IN3_BANCO) > 0;
}

function banco(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!is_dir(IN3_DATA)) {
        mkdir(IN3_DATA, 0775, true);
    }
    $novo = !banco_existe();
    $pdo = new PDO('sqlite:' . IN3_BANCO, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');
    $pdo->exec('PRAGMA busy_timeout=5000');
    if ($novo) {
        criar_esquema($pdo);
    }
    return $pdo;
}

function esquema_sql(): array
{
    return [
        "CREATE TABLE IF NOT EXISTS config (chave TEXT PRIMARY KEY, valor TEXT NOT NULL DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario TEXT NOT NULL UNIQUE,
            nome TEXT NOT NULL DEFAULT '',
            email TEXT NOT NULL DEFAULT '',
            hash TEXT NOT NULL,
            papel TEXT NOT NULL DEFAULT 'leitor',
            clareza INTEGER NOT NULL DEFAULT 1,
            ativo INTEGER NOT NULL DEFAULT 1,
            precisa_trocar_senha INTEGER NOT NULL DEFAULT 1,
            criado_em TEXT NOT NULL DEFAULT '',
            ultimo_acesso TEXT NOT NULL DEFAULT ''
        )",
        "CREATE TABLE IF NOT EXISTS tentativas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario TEXT NOT NULL,
            ip TEXT NOT NULL DEFAULT '',
            quando TEXT NOT NULL,
            sucesso INTEGER NOT NULL DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS auditoria (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            quando TEXT NOT NULL,
            usuario TEXT NOT NULL DEFAULT '-',
            acao TEXT NOT NULL,
            alvo TEXT NOT NULL DEFAULT '',
            detalhe TEXT NOT NULL DEFAULT ''
        )",
        "CREATE TABLE IF NOT EXISTS repos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL UNIQUE,
            visibilidade TEXT NOT NULL DEFAULT 'pendente',
            privado INTEGER NOT NULL DEFAULT 0,
            linguagem TEXT NOT NULL DEFAULT '',
            descricao TEXT NOT NULL DEFAULT '',
            gh_atualizado_em TEXT NOT NULL DEFAULT '',
            commits INTEGER NOT NULL DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS projetos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT NOT NULL UNIQUE,
            titulo TEXT NOT NULL,
            emoji TEXT NOT NULL DEFAULT '🧊',
            icone TEXT NOT NULL DEFAULT 'governanca',
            categoria TEXT NOT NULL DEFAULT 'Governança & Infra',
            status TEXT NOT NULL DEFAULT 'em incubação',
            resumo TEXT NOT NULL DEFAULT '',
            cor TEXT NOT NULL DEFAULT '#2A9581',
            ordem INTEGER NOT NULL DEFAULT 100,
            mostrar_ao_publico INTEGER NOT NULL DEFAULT 0,
            mostrar_link_repo INTEGER NOT NULL DEFAULT 0,
            nivel_divulgacao TEXT NOT NULL DEFAULT 'roadmap',
            criado_em TEXT NOT NULL DEFAULT '',
            atualizado_em TEXT NOT NULL DEFAULT ''
        )",
        "CREATE TABLE IF NOT EXISTS projeto_repos (
            projeto_id INTEGER NOT NULL,
            repo_id INTEGER NOT NULL,
            PRIMARY KEY (projeto_id, repo_id)
        )",
        "CREATE TABLE IF NOT EXISTS camadas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            projeto_id INTEGER NOT NULL,
            profundidade INTEGER NOT NULL,
            titulo TEXT NOT NULL DEFAULT '',
            corpo TEXT NOT NULL DEFAULT '',
            atualizado_em TEXT NOT NULL DEFAULT '',
            UNIQUE (projeto_id, profundidade)
        )",
        "CREATE TABLE IF NOT EXISTS pedidos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL DEFAULT '',
            email TEXT NOT NULL DEFAULT '',
            projeto_id INTEGER NOT NULL DEFAULT 0,
            nivel INTEGER NOT NULL DEFAULT 3,
            mensagem TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'novo',
            quando TEXT NOT NULL DEFAULT ''
        )",
        "CREATE TABLE IF NOT EXISTS acessos (
            token TEXT PRIMARY KEY,
            projeto_id INTEGER NOT NULL,
            nivel INTEGER NOT NULL,
            expira_em TEXT NOT NULL,
            usado INTEGER NOT NULL DEFAULT 0,
            criado_em TEXT NOT NULL DEFAULT ''
        )",
        "CREATE INDEX IF NOT EXISTS ix_projetos_pub ON projetos (mostrar_ao_publico, ordem)",
        "CREATE INDEX IF NOT EXISTS ix_camadas_proj ON camadas (projeto_id, profundidade)",
        "CREATE INDEX IF NOT EXISTS ix_pedidos_status ON pedidos (status)",
        "INSERT OR IGNORE INTO config (chave, valor) VALUES ('esquema_versao', '3.0.0')",
    ];
}

function criar_esquema(?PDO $pdo = null): void
{
    $pdo = $pdo instanceof PDO ? $pdo : banco();
    foreach (esquema_sql() as $sql) {
        $pdo->exec($sql);
    }
}

function consulta(string $sql, array $par = []): array
{
    $st = banco()->prepare($sql);
    $st->execute($par);
    return $st->fetchAll() ?: [];
}

function um(string $sql, array $par = []): ?array
{
    $r = consulta($sql, $par);
    return $r[0] ?? null;
}

function escalar(string $sql, array $par = [])
{
    $st = banco()->prepare($sql);
    $st->execute($par);
    return $st->fetchColumn();
}

function executar(string $sql, array $par = []): int
{
    $st = banco()->prepare($sql);
    $st->execute($par);
    return $st->rowCount();
}

function configuracao(string $chave, string $padrao = ''): string
{
    $v = escalar('SELECT valor FROM config WHERE chave = :c', [':c' => $chave]);
    return ($v === false || $v === null) ? $padrao : (string) $v;
}

function gravar_config(string $chave, string $valor): void
{
    executar('INSERT INTO config (chave, valor) VALUES (:c, :v)
              ON CONFLICT (chave) DO UPDATE SET valor = excluded.valor', [':c' => $chave, ':v' => $valor]);
}

function auditar(string $acao, string $alvo = '', string $detalhe = ''): void
{
    try {
        $u = usuario_atual();
        executar('INSERT INTO auditoria (quando, usuario, acao, alvo, detalhe) VALUES (:q,:u,:a,:l,:d)', [
            ':q' => agora(), ':u' => $u['usuario'] ?? '-', ':a' => $acao,
            ':l' => $alvo, ':d' => limpar_texto($detalhe, 400),
        ]);
    } catch (Throwable $e) {
        // auditoria nunca derruba a requisição
    }
}

/* ---------------------------------------------------------- autenticação */

function sessao_inicia(): void
{
    if (PHP_SAPI === 'cli') {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        return;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'path'     => '/',
    ]);
    session_name('in3_sessao');
    session_start();
}

function ip_requisicao(): string
{
    if (cfg('IN3_CONFIA_PROXY') === '1' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
}

function usuario_atual(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    sessao_inicia();
    $id = (int) ($_SESSION['uid'] ?? 0);
    if ($id <= 0) {
        return $cache = null;
    }
    $u = um('SELECT * FROM usuarios WHERE id = :i AND ativo = 1', [':i' => $id]);
    if (!$u) {
        unset($_SESSION['uid']);
        return $cache = null;
    }
    return $cache = $u;
}

function senha_forte(string $s): ?string
{
    if (mb_strlen($s) < 10) {
        return 'A senha precisa de ao menos 10 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $s) || !preg_match('/\d/', $s)) {
        return 'A senha precisa misturar letras e números.';
    }
    if (in_array(mb_strtolower($s), ['arcano', '1234567890', 'senha12345'], true)) {
        return 'Essa senha é previsível. Escolha outra.';
    }
    return null;
}

function login(string $usuario, string $senha): array
{
    $usuario = trim($usuario);
    $ip = ip_requisicao();
    $janela = (new DateTimeImmutable('-15 minutes'))->format('Y-m-d H:i:s');
    $falhas = (int) escalar('SELECT count(*) FROM tentativas WHERE usuario = :u AND ip = :i AND sucesso = 0 AND quando > :q',
        [':u' => $usuario, ':i' => $ip, ':q' => $janela]);
    if ($falhas >= 8) {
        auditar('login_bloqueado', $usuario, "ip={$ip}");
        return ['ok' => false, 'msg' => 'Excesso de tentativas. Aguarde 15 minutos.'];
    }
    $u = um('SELECT * FROM usuarios WHERE usuario = :u', [':u' => $usuario]);
    $ok = $u && (int) $u['ativo'] === 1 && password_verify($senha, (string) $u['hash']);
    executar('INSERT INTO tentativas (usuario, ip, quando, sucesso) VALUES (:u,:i,:q,:s)',
        [':u' => $usuario, ':i' => $ip, ':q' => agora(), ':s' => $ok ? 1 : 0]);
    if (!$ok) {
        auditar('login_falha', $usuario, "ip={$ip}");
        usleep(300000);
        return ['ok' => false, 'msg' => 'Usuário ou senha inválidos.'];
    }
    sessao_inicia();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    executar('UPDATE usuarios SET ultimo_acesso = :q WHERE id = :i', [':q' => agora(), ':i' => (int) $u['id']]);
    auditar('login_ok', $usuario, 'papel=' . $u['papel']);
    return ['ok' => true, 'precisa_trocar' => (int) $u['precisa_trocar_senha'] === 1];
}

function logout(): void
{
    sessao_inicia();
    $u = usuario_atual();
    if ($u) {
        auditar('logout', (string) $u['usuario']);
    }
    $_SESSION = [];
    if (PHP_SAPI !== 'cli') {
        session_destroy();
    }
}

function trocar_senha(string $nova, string $confirma): array
{
    $u = usuario_atual();
    if (!$u) {
        return ['ok' => false, 'msg' => 'Sessão expirada.'];
    }
    if ($nova !== $confirma) {
        return ['ok' => false, 'msg' => 'As duas senhas não conferem.'];
    }
    if ($erro = senha_forte($nova)) {
        return ['ok' => false, 'msg' => $erro];
    }
    executar('UPDATE usuarios SET hash = :h, precisa_trocar_senha = 0 WHERE id = :i', [
        ':h' => password_hash($nova, PASSWORD_BCRYPT, ['cost' => 12]),
        ':i' => (int) $u['id'],
    ]);
    auditar('senha_trocada', (string) $u['usuario']);
    return ['ok' => true, 'msg' => 'Senha atualizada.'];
}

/* ------------------------------------------------------------- permissão */

function pode(string $acao, ?array $u = null): bool
{
    $u = $u ?? usuario_atual();
    if (!$u) {
        return false;
    }
    $papel = (string) $u['papel'];
    $regras = IN3_PERMISSOES[$papel] ?? [];
    return in_array('*', $regras, true) || in_array($acao, $regras, true);
}

function exigir_papel(string $acao): void
{
    if (pode($acao)) {
        return;
    }
    auditar('acesso_negado', $acao, 'sem permissão');
    http_response_code(403);
    require IN3_RAIZ . '/src/vistas.php';
    echo layout('Acesso restrito', '<section class="pagina"><h1>403 · acesso restrito</h1>'
        . '<p>A sua conta não tem a permissão <code>' . e($acao) . '</code>.</p>'
        . '<p><a class="botao" href="' . e(url()) . '">Voltar ao site</a></p></section>', ['admin' => true]);
    exit;
}

function clareza_efetiva(): int
{
    $u = usuario_atual();
    $base = $u ? max(1, min(4, (int) $u['clareza'])) : 1;
    $extra = (int) ($_SESSION['clareza_extra'] ?? 0);
    $projeto = (int) ($_SESSION['clareza_projeto'] ?? 0);
    return max($base, min(4, max($extra, $projeto)));
}

function clareza_projeto(int $projetoId): int
{
    $u = usuario_atual();
    $base = $u ? max(1, min(4, (int) $u['clareza'])) : 1;
    if ((int) ($_SESSION['clareza_projeto'] ?? 0) === $projetoId) {
        $base = max($base, (int) ($_SESSION['clareza_extra'] ?? 0));
    }
    return max(1, min(4, $base));
}

/* --------------------------------------------------------------- acervo */

function projeto_por_slug(string $slug): ?array
{
    return um('SELECT * FROM projetos WHERE slug = :s', [':s' => $slug]);
}

function projeto_por_id(int $id): ?array
{
    return um('SELECT * FROM projetos WHERE id = :i', [':i' => $id]);
}

function projetos_visiveis(int $clareza, bool $admin = false, string $busca = ''): array
{
    $sql = 'SELECT * FROM projetos WHERE 1=1';
    $par = [];
    if (!$admin) {
        $sql .= ' AND mostrar_ao_publico = 1';
    }
    if ($busca !== '') {
        $sql .= ' AND (titulo LIKE :b OR resumo LIKE :b OR categoria LIKE :b)';
        $par[':b'] = '%' . $busca . '%';
    }
    $sql .= ' ORDER BY mostrar_ao_publico DESC, ordem ASC, titulo COLLATE NOCASE';
    $linhas = consulta($sql, $par);
    return array_values(array_filter($linhas, static function (array $p) use ($clareza, $admin): bool {
        if ($admin) {
            return true;
        }
        $teto = IN3_NIVEIS[(string) $p['nivel_divulgacao']] ?? 1;
        return $teto >= 1;
    }));
}

function repos_do_projeto(int $projetoId): array
{
    return consulta('SELECT r.* FROM repos r JOIN projeto_repos pr ON pr.repo_id = r.id
                     WHERE pr.projeto_id = :p ORDER BY r.nome COLLATE NOCASE', [':p' => $projetoId]);
}

function tecnologias_do_projeto(int $projetoId): array
{
    $t = [];
    foreach (repos_do_projeto($projetoId) as $r) {
        if (!empty($r['linguagem'])) {
            $t[] = (string) $r['linguagem'];
        }
    }
    return array_values(array_unique($t));
}

/**
 * Monta as camadas de um projeto para uma clareza.
 * Camada bloqueada NÃO carrega conteúdo: o texto nunca sai do banco.
 */
function camadas_do_projeto(array $p, int $clareza, bool $admin = false): array
{
    $teto = IN3_NIVEIS[(string) $p['nivel_divulgacao']] ?? 1;
    $limite = $admin ? 4 : min(4, max(1, $clareza), max(1, $teto));
    $todas = consulta('SELECT * FROM camadas WHERE projeto_id = :p ORDER BY profundidade', [':p' => (int) $p['id']]);
    $out = [];
    foreach ($todas as $c) {
        $prof = (int) $c['profundidade'];
        $liberada = $prof <= $limite;
        $out[] = [
            'profundidade' => $prof,
            'titulo'       => (string) $c['titulo'] !== '' ? (string) $c['titulo'] : (IN3_NIVEL_ROTULO[$prof] ?? 'Camada ' . $prof),
            'rotulo'       => IN3_NIVEL_ROTULO[$prof] ?? ('Camada ' . $prof),
            'liberada'     => $liberada,
            'corpo'        => $liberada ? (string) $c['corpo'] : '',
            'atualizado'   => $liberada ? (string) $c['atualizado_em'] : '',
        ];
    }
    return ['limite' => $limite, 'teto' => $teto, 'camadas' => $out];
}

/** Atualizações recentes = camadas liberadas mais recentemente (feed humano). */
function atualizacoes_recentes(int $clareza, int $limite = 6): array
{
    $out = [];
    foreach (projetos_visiveis($clareza) as $p) {
        $m = camadas_do_projeto($p, $clareza);
        foreach ($m['camadas'] as $c) {
            if ($c['profundidade'] > 1 || !$c['liberada']) {
                continue;
            }
            $out[] = ['projeto' => $p, 'camada' => $c, 'quando' => (string) $p['atualizado_em']];
        }
    }
    usort($out, static fn(array $a, array $b): int => strcmp((string) $b['quando'], (string) $a['quando']));
    return array_slice($out, 0, $limite);
}

/* ---------------------------------------------------------------- busca */

/** Busca textual no servidor, já filtrada por escopo (camada) e publicação. */
function buscar(string $termo, int $clareza, bool $admin = false, int $limite = 40): array
{
    $termo = trim($termo);
    if ($termo === '' || mb_strlen($termo) < 2) {
        return ['termo' => $termo, 'total' => 0, 'resultados' => [], 'escopo_max' => 0];
    }
    $sql = 'SELECT c.profundidade, c.corpo, c.projeto_id, p.titulo, p.slug, p.mostrar_ao_publico, p.nivel_divulgacao
            FROM camadas c JOIN projetos p ON p.id = c.projeto_id
            WHERE c.corpo LIKE :t AND c.profundidade <= :cl';
    $par = [':t' => '%' . $termo . '%', ':cl' => $admin ? 4 : max(1, min(4, $clareza))];
    if (!$admin) {
        $sql .= ' AND p.mostrar_ao_publico = 1';
    }
    $sql .= ' ORDER BY c.profundidade ASC, p.titulo COLLATE NOCASE LIMIT ' . max(1, min(200, $limite));
    $linhas = consulta($sql, $par);
    $res = [];
    $max = 0;
    foreach ($linhas as $l) {
        $max = max($max, (int) $l['profundidade']);
        $pos = mb_stripos((string) $l['corpo'], $termo);
        $ini = max(0, (int) $pos - 90);
        $trecho = mb_substr((string) $l['corpo'], $ini, 220);
        $res[] = [
            'titulo'       => (string) $l['titulo'],
            'slug'         => (string) $l['slug'],
            'profundidade' => (int) $l['profundidade'],
            'rotulo'       => IN3_NIVEL_ROTULO[(int) $l['profundidade']] ?? '—',
            'trecho'       => ($ini > 0 ? '…' : '') . $trecho . '…',
        ];
    }
    return ['termo' => $termo, 'total' => count($res), 'resultados' => $res, 'escopo_max' => $max];
}

/* -------------------------------------------------------------- pedidos */

function criar_pedido(string $nome, string $email, int $projetoId, int $nivel, string $mensagem): int
{
    executar('INSERT INTO pedidos (nome, email, projeto_id, nivel, mensagem, status, quando)
              VALUES (:n, :e, :p, :l, :m, \'novo\', :q)', [
        ':n' => limpar_texto($nome, 120), ':e' => limpar_texto($email, 160),
        ':p' => $projetoId, ':l' => max(1, min(4, $nivel)), ':m' => limpar_texto($mensagem, 1200), ':q' => agora(),
    ]);
    $id = (int) banco()->lastInsertId();
    auditar('pedido_criado', 'pedido#' . $id, 'projeto=' . $projetoId . ' nivel=' . $nivel);
    return $id;
}

function liberar_pedido(int $pedidoId, int $dias = 7): ?string
{
    $pe = um('SELECT * FROM pedidos WHERE id = :i', [':i' => $pedidoId]);
    if (!$pe) {
        return null;
    }
    $token = bin2hex(random_bytes(24));
    executar('INSERT INTO acessos (token, projeto_id, nivel, expira_em, criado_em) VALUES (:t,:p,:n,:e,:c)', [
        ':t' => $token, ':p' => (int) $pe['projeto_id'], ':n' => (int) $pe['nivel'],
        ':e' => (new DateTimeImmutable('+' . $dias . ' days'))->format('Y-m-d H:i:s'), ':c' => agora(),
    ]);
    executar('UPDATE pedidos SET status = \'liberado\' WHERE id = :i', [':i' => $pedidoId]);
    auditar('pedido_liberado', 'pedido#' . $pedidoId, 'nivel=' . $pe['nivel'] . ' validade=' . $dias . 'd');
    return $token;
}

function abrir_acesso(string $token): ?array
{
    $a = um('SELECT * FROM acessos WHERE token = :t AND usado = 0 AND expira_em > :q',
        [':t' => $token, ':q' => agora()]);
    if (!$a) {
        return null;
    }
    sessao_inicia();
    $_SESSION['clareza_projeto'] = (int) $a['projeto_id'];
    $_SESSION['clareza_extra'] = max((int) ($_SESSION['clareza_extra'] ?? 0), (int) $a['nivel']);
    auditar('acesso_aberto', 'projeto#' . $a['projeto_id'], 'nivel=' . $a['nivel']);
    return $a;
}

/* --------------------------------------------------------------- ícones */

/** Logo do sistema: cubo isométrico com a face ³ — base do estilo m3d.pro. */
function logo_svg(int $t = 40): string
{
    return '<svg class="logo-cubo" width="' . $t . '" height="' . $t . '" viewBox="0 0 100 100" role="img" aria-label="IN³">'
        . '<polygon points="50,6 94,31 50,56 6,31" fill="#59C1A5"/>'
        . '<polygon points="6,31 50,56 50,96 6,71" fill="#2A9581"/>'
        . '<polygon points="94,31 50,56 50,96 94,71" fill="#3AA3BA"/>'
        . '<polygon points="50,15 74,29 50,43 26,29" fill="#FED166"/>'
        . '<text x="50" y="34" text-anchor="middle" font-family="Verdana,sans-serif" font-size="20" font-weight="700" fill="#0B2026">3</text>'
        . '<text x="50" y="86" text-anchor="middle" font-family="Verdana,sans-serif" font-size="17" font-weight="700" fill="#FFFFFF">IN³</text>'
        . '</svg>';
}

/** Ícone de projeto: cubo isométrico com glifo próprio da categoria. */
function icone_projeto(string $categoria, string $cor, int $t = 54): string
{
    $glifos = [
        'telemedicina' => '🩺', 'fiscal' => '🧾', 'catalogo' => '🛍️', 'educacao' => '🎓',
        'sensor' => '📶', 'esporte' => '🏃', 'vitrine' => '✨', 'ia' => '🧠',
        'governanca' => '🏛️', 'cirurgia' => '🎥', 'certificado' => '📜', 'infra' => '🧊',
    ];
    $chave = mb_strtolower($categoria);
    $glifo = '🧊';
    foreach ($glifos as $k => $g) {
        if (str_contains($chave, $k)) {
            $glifo = $g;
            break;
        }
    }
    $claro = $cor;
    $medio = escurecer($cor, 0.18);
    $escuro = escurecer($cor, 0.34);
    return '<span class="icone-cubo" style="--t:' . $t . 'px">'
        . '<svg viewBox="0 0 100 100" width="' . $t . '" height="' . $t . '" role="img" aria-label="' . e($categoria) . '">'
        . '<polygon points="50,6 94,31 50,56 6,31" fill="' . e($claro) . '"/>'
        . '<polygon points="6,31 50,56 50,96 6,71" fill="' . e($medio) . '"/>'
        . '<polygon points="94,31 50,56 50,96 94,71" fill="' . e($escuro) . '"/>'
        . '<text x="50" y="38" text-anchor="middle" font-size="26">' . $glifo . '</text>'
        . '</svg></span>';
}

function escurecer(string $hex, float $f): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) {
        return '#2A9581';
    }
    $out = '#';
    for ($i = 0; $i < 3; $i++) {
        $c = (int) hexdec(substr($hex, $i * 2, 2));
        $c = (int) max(0, min(255, (int) round($c * (1 - $f))));
        $out .= str_pad(dechex($c), 2, '0', STR_PAD_LEFT);
    }
    return $out;
}

function icone_ui(string $nome, int $t = 18): string
{
    $p = [
        'busca'  => 'M10 4a6 6 0 104.5 10.2L20 20l1.5-1.5-5.8-5.5A6 6 0 0010 4zm0 2a4 4 0 110 8 4 4 0 010-8z',
        'cubo'   => 'M12 2l9 5v10l-9 5-9-5V7l9-5zm0 2.3L5.3 8 12 11.7 18.7 8 12 4.3z',
        'escudo' => 'M12 2l8 3v7c0 5-3.4 8.6-8 10-4.6-1.4-8-5-8-10V5l8-3z',
        'grade'  => 'M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z',
        'pasta'  => 'M3 5h6l2 2h10v12H3V5z',
        'usuario'=> 'M12 3a4.5 4.5 0 110 9 4.5 4.5 0 010-9zm0 11c4.4 0 8 2.2 8 4v3H4v-3c0-1.8 3.6-4 8-4z',
        'grafo'  => 'M4 20V8m5 12V4m5 16v-7m5 7V6',
    ];
    $d = $p[$nome] ?? $p['cubo'];
    return '<svg class="iui" width="' . $t . '" height="' . $t . '" viewBox="0 0 24 24" aria-hidden="true">'
        . '<path d="' . $d . '" fill="currentColor"/></svg>';
}

/* --------------------------------------------------------- instalação */

function existe_admin(): bool
{
    try {
        return (int) escalar("SELECT count(*) FROM usuarios WHERE papel IN ('root','admin')") > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function criar_usuario(string $usuario, string $senha, string $papel, int $clareza, string $nome = '', string $email = '', bool $trocar = true): int
{
    $papel = in_array($papel, IN3_PAPEIS, true) ? $papel : 'leitor';
    executar('INSERT INTO usuarios (usuario, nome, email, hash, papel, clareza, ativo, precisa_trocar_senha, criado_em)
              VALUES (:u,:n,:e,:h,:p,:c,1,:t,:q)', [
        ':u' => $usuario, ':n' => $nome, ':e' => $email,
        ':h' => password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]),
        ':p' => $papel, ':c' => max(1, min(4, $clareza)), ':t' => $trocar ? 1 : 0, ':q' => agora(),
    ]);
    return (int) banco()->lastInsertId();
}

function definir_senha(string $usuario, string $senha): bool
{
    return executar('UPDATE usuarios SET hash = :h, precisa_trocar_senha = 0, ativo = 1 WHERE usuario = :u', [
        ':h' => password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]), ':u' => $usuario,
    ]) > 0;
}

/* ------------------------------------------------------------- segurança */

function csrf_token(): string
{
    sessao_inicia();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf'];
}

function csrf_valido(?string $token): bool
{
    sessao_inicia();
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals((string) $_SESSION['csrf'], $token);
}

function cobertura_repos(): array
{
    $total = (int) escalar('SELECT count(*) FROM repos');
    $mapeados = (int) escalar('SELECT count(DISTINCT repo_id) FROM projeto_repos');
    $pub = (int) escalar("SELECT count(*) FROM repos WHERE privado = 0");
    $pri = (int) escalar('SELECT count(*) FROM repos WHERE privado = 1');
    $orfaos = consulta('SELECT nome FROM repos WHERE id NOT IN (SELECT repo_id FROM projeto_repos) ORDER BY nome');
    return [
        'total' => $total, 'mapeados' => $mapeados, 'publicos' => $pub, 'privados' => $pri,
        'orfaos' => array_column($orfaos, 'nome'),
    ];
}

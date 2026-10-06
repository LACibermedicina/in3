<?php
declare(strict_types=1);
/**
 * IN³ · núcleo — bootstrap, configuração, utilidades, CSRF, sessão HTTP e log.
 * Sem dependências externas. Compatível com PHP 8.1+.
 */

mb_internal_encoding('UTF-8');
date_default_timezone_set(getenv('IN3_TZ') ?: 'America/Sao_Paulo');

define('IN3_RAIZ', dirname(__DIR__));
define('IN3_VERSAO', '2.0.0');
define('IN3_DADOS', IN3_RAIZ . '/data');
define('IN3_VIEWS', IN3_RAIZ . '/views');
define('IN3_PUBLICO', IN3_RAIZ . '/public');

/** Camadas de detalhamento. A ordem numérica é o contrato de segurança. */
const IN3_NIVEIS = [
    'institucional_roadmap' => 1,
    'institucional'         => 2,
    'tecnico'               => 3,
    'playbook_completo'     => 4,
];

const IN3_NIVEL_ROTULO = [
    1 => 'Roadmap institucional',
    2 => 'Institucional',
    3 => 'Técnico',
    4 => 'Playbook completo',
];

/** Lê .env uma única vez (sem sobrescrever variáveis já definidas no ambiente). */
function in3_carrega_env(): array
{
    static $carga = null;
    if ($carga !== null) {
        return $carga;
    }
    $carga = [];
    $arq = IN3_RAIZ . '/.env';
    if (is_file($arq)) {
        foreach (file($arq, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
            $linha = trim($linha);
            if ($linha === '' || $linha[0] === '#') {
                continue;
            }
            $p = strpos($linha, '=');
            if ($p === false) {
                continue;
            }
            $chave = trim(substr($linha, 0, $p));
            $valor = trim(substr($linha, $p + 1));
            if (strlen($valor) > 1 && ($valor[0] === '"' || $valor[0] === "'") && substr($valor, -1) === $valor[0]) {
                $valor = substr($valor, 1, -1);
            }
            $carga[$chave] = $valor;
            if (getenv($chave) === false) {
                putenv($chave . '=' . $valor);
            }
        }
    }
    return $carga;
}

function cfg(string $chave, ?string $padrao = null): ?string
{
    in3_carrega_env();
    $v = getenv($chave);
    return ($v === false || $v === '') ? $padrao : $v;
}

/** Rota do painel: propositalmente fora de qualquer link do site público. */
function rota_painel(): string
{
    $r = cfg('IN3_ROTA_PAINEL', '/console') ?? '/console';
    if ($r === '' || $r[0] !== '/') {
        $r = '/' . $r;
    }
    return rtrim($r, '/');
}

function e(?string $t): string
{
    return htmlspecialchars((string) $t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $caminho = '/'): string
{
    if ($caminho === '' || $caminho[0] !== '/') {
        $caminho = '/' . $caminho;
    }
    return $caminho;
}

function url_painel(string $sufixo = ''): string
{
    return rota_painel() . $sufixo;
}

function redirecionar(string $para, int $codigo = 303): never
{
    header('Location: ' . $para, true, $codigo);
    exit;
}

/* ------------------------------------------------------------------ flash */

function flash(string $tipo, string $texto): void
{
    in3_sessao_inicia();
    $_SESSION['flash'][] = ['tipo' => $tipo, 'texto' => $texto];
}

function flash_pegar(): array
{
    in3_sessao_inicia();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ----------------------------------------------------------------- sessão */

function in3_sessao_inicia(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (PHP_SAPI === 'cli') {
        // na linha de comando não há cookies nem cabeçalhos: a "sessão" é volátil
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        return;
    }
    $seguro = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $seguro,
        'samesite' => 'Lax',
    ]);
    session_name('in3sid');
    session_start();
}

/* -------------------------------------------------------------------- CSRF */

function csrf_token(): string
{
    in3_sessao_inicia();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verifica(): void
{
    in3_sessao_inicia();
    $enviado = $_POST['csrf'] ?? '';
    $guardado = $_SESSION['csrf'] ?? '';
    if (!is_string($enviado) || $guardado === '' || !hash_equals($guardado, $enviado)) {
        http_response_code(419);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><meta charset="utf-8"><title>Sessão expirada</title>'
           . '<p style="font:16px system-ui;padding:2rem">Sessão expirada ou formulário inválido. '
           . '<a href="' . e(url('/')) . '">Voltar ao início</a>.</p>';
        exit;
    }
}

/* ------------------------------------------------------------- requisição */

function metodo(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function ip_visitante(): string
{
    // Atrás de proxy reverso confiável, use IN3_CONFIA_PROXY=1.
    if (cfg('IN3_CONFIA_PROXY') === '1' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $lista = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($lista[0]);
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

function agente(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200);
}

function corpo_json(): array
{
    $cru = file_get_contents('php://input');
    if ($cru === false || $cru === '') {
        return [];
    }
    $d = json_decode($cru, true);
    return is_array($d) ? $d : [];
}

function entrada(string $chave, string $padrao = ''): string
{
    $v = $_POST[$chave] ?? $_GET[$chave] ?? $padrao;
    return is_string($v) ? trim($v) : $padrao;
}

function json_saida(array $dados, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function nao_encontrado(string $msg = 'Não encontrado'): never
{
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    $t = e($msg);
    echo "<!doctype html><html lang=\"pt-BR\"><meta charset=\"utf-8\">"
       . "<meta name=\"robots\" content=\"noindex\"><title>404 · IN³</title>"
       . "<body style=\"font:16px/1.6 system-ui;background:#F6FFFC;color:#122A2E;display:grid;place-items:center;min-height:100vh;margin:0\">"
       . "<div style=\"text-align:center\"><div style=\"font-size:64px\">🧊</div><h1>{$t}</h1>"
       . "<p><a href=\"/\" style=\"color:#167D6E\">Voltar ao portfólio</a></p></div></body></html>";
    exit;
}

/* ------------------------------------------------------------ utilidades */

function agora(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

function data_br(string $iso, bool $comHora = false): string
{
    if ($iso === '') {
        return '—';
    }
    $ts = strtotime($iso);
    if ($ts === false) {
        return e($iso);
    }
    return date($comHora ? 'd/m/Y H:i' : 'd/m/Y', $ts);
}

function slugificar(string $t): string
{
    $t = iconv('UTF-8', 'ASCII//TRANSLIT', $t) ?: $t;
    $t = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $t) ?? '');
    return trim($t, '-');
}

function texto_limpo(string $t, int $max = 0): string
{
    $t = strip_tags($t);
    $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
    $t = trim($t);
    return $max > 0 && mb_strlen($t) > $max ? mb_substr($t, 0, $max - 1) . '…' : $t;
}

/** O playbook manda: campo "(pendente)" não é publicado. */
function pendente(?string $t): bool
{
    return $t === null || $t === '' || mb_stripos($t, '(pendente)') !== false;
}

function campo_publicavel(?string $t): bool
{
    return !pendente($t);
}

function js_json($dado): string
{
    return json_encode($dado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
}

/* ------------------------------------------------------------ log/acesso */

function registrar_acesso(string $rota, string $termo = '', int $resultado = 0): void
{
    try {
        $u = usuario_atual();
        executa(
            'INSERT INTO acesso_log (quando, rota, papel, usuario_id, termo, resultado, ip, agente)
             VALUES (:q, :r, :p, :u, :t, :res, :ip, :ag)',
            [
                ':q'   => agora(),
                ':r'   => mb_substr($rota, 0, 160),
                ':p'   => $u['papel'] ?? 'anonimo',
                ':u'   => $u['id'] ?? null,
                ':t'   => mb_substr($termo, 0, 160),
                ':res' => $resultado,
                ':ip'  => ip_visitante(),
                ':ag'  => agente(),
            ]
        );
    } catch (Throwable $e) {
        // registro de acesso nunca derruba a página
    }
}

function auditar(string $acao, string $entidade = '', ?int $entidadeId = null, string $detalhe = ''): void
{
    try {
        $u = usuario_atual();
        executa(
            'INSERT INTO auditoria (quando, usuario_id, acao, entidade, entidade_id, detalhe, ip)
             VALUES (:q, :u, :a, :e, :ei, :d, :ip)',
            [
                ':q'  => agora(),
                ':u'  => $u['id'] ?? null,
                ':a'  => $acao,
                ':e'  => $entidade,
                ':ei' => $entidadeId,
                ':d'  => texto_limpo($detalhe, 400),
                ':ip' => ip_visitante(),
            ]
        );
    } catch (Throwable $e) {
        // idem
    }
}

/* ------------------------------------------------------------- segurança */

function cabecalhos_seguranca(bool $publico = true): void
{
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    if (!$publico) {
        header('X-Robots-Tag: noindex, nofollow');
    }
    // CSP: apenas o próprio host; sem CDNs, coerente com "site autocontido".
    header(
        "Content-Security-Policy: default-src 'self'; img-src 'self' data: https://avatars.githubusercontent.com; "
        . "style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; "
        . "font-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'self'"
    );
}

require_once IN3_RAIZ . '/src/banco.php';
require_once IN3_RAIZ . '/src/auth.php';
require_once IN3_RAIZ . '/src/projetos.php';
require_once IN3_RAIZ . '/src/busca.php';
require_once IN3_RAIZ . '/src/icones.php';
require_once IN3_RAIZ . '/src/render.php';
require_once IN3_RAIZ . '/src/api.php';

/* módulos v3 */
require_once __DIR__ . '/senhas.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/feed.php';
require_once __DIR__ . '/rotas_extra.php';

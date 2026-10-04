<?php
/**
 * IN³ · auth-server.php — backend de login do painel administrativo.
 *
 * Recebe pedidos de token por e-mail (caminho dinâmico) e — opcionalmente —
 * aceita o token universal "arcano" quando a variável de ambiente IN3_ARCANO=1.
 *
 * Rotas:
 *   POST /api/acesso/pedir?email=... → envia e-mail com link de 60 min
 *   GET  /api/acesso?token=...       → valida e devolve { ok:true, painel:'/console' }
 *   GET  /api/saude                  → healthcheck
 *
 * Como rodar:
 *   php -S 127.0.0.1:8788 -t . auth-server.php
 *   (ou coloque atrás de um nginx + php-fpm em produção)
 *
 * Variáveis (defina no host):
 *   IN3_ARCANO   = 0  (1 só para desenvolvimento)  ← por padrão o "arcano" é recusado
 *   SMTP_HOST    = smtp.gmail.com (ou mailgun / ses / etc.)
 *   SMTP_PORT    = 587
 *   SMTP_USER    = in.com.br
 *   SMTP_PASS    = <senha-de-app>
 *   MAIL_FROM    = IN³ <in.com.br>
 *   IN3_URL      = https://in.m3d.pro
 *   IN3_TTL_MIN  = 60         (validade do token em minutos)
 *   TOKENS_FILE  = /var/lib/in3/tokens.json  (persistência simples em JSON)
 *
 * O front (index.html) já chama /api/acesso/pedir e /api/acesso na mesma origem.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

const ARC_TOKEN    = 'arcano';
const DEFAULT_TTL  = 60;          // minutos
const STATE_METHOD = 'GET|POST';

function env_default(string $k, ?string $fallback = null): ?string {
    $v = getenv($k);
    return ($v === false || $v === '') ? $fallback : $v;
}

function arcano_habilitado(): bool {
    return env_default('IN3_ARCANO', '0') === '1';
}

function ttl_minutos(): int {
    $n = (int) env_default('IN3_TTL_MIN', (string) DEFAULT_TTL);
    return max(1, min(24 * 60, $n));
}

function tokens_arquivo(): string {
    return env_default('TOKENS_FILE', sys_get_temp_dir() . '/in3-tokens.json');
}

function tokens_carregar(): array {
    $f = tokens_arquivo();
    if (!is_file($f)) return [];
    $raw = @file_get_contents($f);
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function tokens_salvar(array $tokens): bool {
    $f = tokens_arquivo();
    $dir = dirname($f);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $json = json_encode($tokens, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) && (bool) @file_put_contents($f, $json, LOCK_EX);
}

function tokens_purgar(): array {
    $tokens = tokens_carregar();
    $agora  = time();
    $mantidos = [];
    foreach ($tokens as $t => $rec) {
        if (is_array($rec) && ($rec['exp'] ?? 0) > $agora) {
            $mantidos[$t] = $rec;
        }
    }
    tokens_salvar($mantidos);
    return $mantidos;
}

function novo_token(): string {
    return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
}

function set_session_cookie(): void {
    $sess = bin2hex(random_bytes(16));
    $secure = (parse_url(env_default('IN3_URL', 'http://localhost') ?: 'http://localhost', PHP_URL_SCHEME) === 'https');
    setcookie('in3_sess', $sess, [
        'expires'  => time() + 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => $secure,
    ]);
    // Opcionalmente, espelhe em header para ambientes sem cookie nativo.
    header('X-IN3-Sess: ' . $sess);
}

function json_out(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function rota(): string {
    $u = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    return '/' . trim($u, '/');
}

function email_valido(string $email): bool {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function enviar_email(string $para, string $link): array {
    $host = env_default('SMTP_HOST');
    if (!$host) {
        // Sem SMTP: devolve sucesso mas também o link por fallback (modo dev/offline).
        return ['ok' => true, 'modo' => 'sem-smtp', 'link' => $link];
    }
    // Se você tiver Composer/PHPMailer, prefira usá-lo aqui. Esta versão usa
    // mail() como fallback — em produção troque por SMTP autenticado.
    $from  = env_default('MAIL_FROM', 'IN³ <no-reply@' . parse_url(env_default('IN3_URL', 'http://localhost'), PHP_URL_HOST) . '>');
    $assunto = 'IN³ · seu link de acesso (válido por ' . ttl_minutos() . ' minutos)';
    $corpoTxt = "Acesse {$link}\n\nVálido por " . ttl_minutos() . " minutos.\nSe você não fez esta solicitação, ignore esta mensagem.";
    $corpoHtml = "<p>Acesse <a href=\"{$link}\">seu painel IN³</a>.</p>"
               . "<p>Válido por " . ttl_minutos() . " minutos. Após esse prazo, peça um novo token pela própria página.</p>"
               . "<p style=\"color:#678\">Se você não fez esta solicitação, ignore esta mensagem.</p>";
    $headers = "MIME-Version: 1.0\r\n"
             . "Content-type: text/html; charset=utf-8\r\n"
             . "From: {$from}\r\n"
             . "Reply-To: {$from}\r\n";
    $ok = @mail($para, $assunto, $corpoHtml, $headers);
    return $ok ? ['ok' => true] : ['ok' => false, 'erro' => 'mail-falhou'];
}

// --------- ROTEAMENTO ---------
$rota = rota();
$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($rota === '/api/saude' && $metodo === 'GET') {
    $tokens = tokens_purgar();
    json_out([
        'ok' => true,
        'servico' => 'in3-auth (php)',
        'arcano_habilitado' => arcano_habilitado(),
        'tokens_ativos' => count($tokens),
        'ttl_minutos' => ttl_minutos(),
        'agora' => date(DATE_ATOM),
    ]);
}

if ($rota === '/api/acesso/pedir' && $metodo === 'POST') {
    $email = strtolower(trim((string) ($_GET['email'] ?? $_POST['email'] ?? '')));
    if (!email_valido($email)) json_out(['ok' => false, 'erro' => 'e-mail-invalido'], 400);

    $tokens = tokens_purgar();
    $t = novo_token();
    $tokens[$t] = [
        'email' => $email,
        'ip'    => $_SERVER['REMOTE_ADDR'] ?? '?',
        'exp'   => time() + ttl_minutos() * 60,
        'pedido'=> date(DATE_ATOM),
    ];
    tokens_salvar($tokens);

    $link = rtrim(env_default('IN3_URL', ''), '/') . '/api/acesso?token=' . $t;
    $envio = enviar_email($email, $link);
    if (!$envio['ok']) json_out(['ok' => false, 'erro' => 'smtp', 'detalhe' => $envio], 500);

    // Em modo dev retornamos o link para o front mostrar (apenas se sem SMTP).
    $resp = ['ok' => true];
    if (!empty($envio['link'])) $resp['link'] = $envio['link'];
    json_out($resp);
}

if ($rota === '/api/acesso' && $metodo === 'GET') {
    $t = trim((string) ($_GET['token'] ?? ''));

    if (arcano_habilitado() && hash_equals(ARC_TOKEN, $t)) {
        // ⚠️ Caminho do token universal "arcano" — só se IN3_ARCANO=1.
        error_log('[in3.auth] ACESSO VIA ARCANO ip=' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
        set_session_cookie();
        json_out(['ok' => true, 'painel' => '/console?origem=arcano']);
    }

    $tokens = tokens_purgar();
    if (!isset($tokens[$t])) json_out(['ok' => false, 'erro' => 'token-invalido'], 401);
    $rec = $tokens[$t];
    unset($tokens[$t]); // uso único
    tokens_salvar($tokens);

    set_session_cookie();
    json_out(['ok' => true, 'painel' => '/console', 'email' => $rec['email']]);
}

// 404 padrão (não vaza nada)
json_out(['ok' => false], 404);

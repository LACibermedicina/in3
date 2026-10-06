<?php
declare(strict_types=1);
/**
 * IN³ · autenticação e clareza (clearance).
 *
 * Regras:
 *  - senhas só existem como hash (PASSWORD_DEFAULT); nunca em log, tela ou doc;
 *  - sessão em banco (token aleatório) + cookie HttpOnly/SameSite;
 *  - login do painel protegido por limite de tentativas;
 *  - "clareza" é o teto de camadas que o visitante pode ler (1..4);
 *  - link de acesso (token) eleva a clareza de forma temporária e auditável.
 */

const IN3_CLAREZA_ANONIMO = 1;

function usuario_atual(): ?array
{
    static $memo = false;
    if ($memo !== false) {
        return $memo;
    }
    in3_sessao_inicia();
    $token = $_SESSION['sessao'] ?? null;
    if (!is_string($token) || $token === '') {
        return $memo = null;
    }
    $linha = um(
        "SELECT s.token, s.csrf, u.id, u.usuario, u.nome, u.email, u.papel, u.clareza, u.ativo
           FROM sessoes s JOIN usuarios u ON u.id = s.usuario_id
          WHERE s.token = :t AND s.expira_em > :q",
        [':t' => $token, ':q' => agora()]
    );
    if (!$linha || (int) $linha['ativo'] !== 1) {
        unset($_SESSION['sessao']);
        return $memo = null;
    }
    return $memo = $linha;
}

function eh_admin(): bool
{
    $u = usuario_atual();
    return $u !== null && $u['papel'] === 'admin';
}

/**
 * Clareza efetiva da requisição: anônimo = 1; autenticado = seu teto;
 * e a liberação por token do projeto pode elevar (mas nunca acima de 4).
 */
function clareza_visitante(?int $projetoId = null): int
{
    $u = usuario_atual();
    $base = $u ? (int) $u['clareza'] : IN3_CLAREZA_ANONIMO;
    if ($u && $u['papel'] === 'admin') {
        $base = 4;
    }
    if ($projetoId !== null) {
        $extra = acessos_temporarios($projetoId);
        if ($extra > $base) {
            $base = $extra;
        }
    }
    return max(1, min(4, $base));
}

/** Maior clareza concedida a este visitante (cookie) para o projeto informado. */
function acessos_temporarios(int $projetoId): int
{
    in3_sessao_inicia();
    $tokens = $_SESSION['acessos'] ?? [];
    if (!is_array($tokens) || !$tokens) {
        return 0;
    }
    $marcadores = implode(',', array_fill(0, count($tokens), '?'));
    $sql = "SELECT MAX(clareza) AS c FROM acessos
             WHERE token IN ($marcadores) AND estado = 'ativo' AND expira_em > ?
               AND (projeto_id = ? OR projeto_id IS NULL)";
    $par = array_values(array_map('strval', $tokens));
    $par[] = agora();
    $par[] = $projetoId;
    $st = db()->prepare($sql);
    $st->execute($par);
    $c = $st->fetchColumn();
    return (int) ($c ?: 0);
}

/* ------------------------------------------------------------------ login */

function limite_login_excedido(string $usuario): bool
{
    $desde = gmdate('Y-m-d\TH:i:s\Z', time() - 900);
    $n = (int) escalar(
        "SELECT count(*) FROM tentativas_login
          WHERE quando > :d AND sucesso = 0 AND (ip = :ip OR usuario = :u)",
        [':d' => $desde, ':ip' => ip_visitante(), ':u' => $usuario]
    );
    return $n >= (int) (cfg('IN3_LIMITE_LOGIN', '8'));
}

function registrar_tentativa(string $usuario, bool $sucesso): void
{
    executa(
        'INSERT INTO tentativas_login (quando, usuario, ip, sucesso) VALUES (:q, :u, :ip, :s)',
        [':q' => agora(), ':u' => mb_substr($usuario, 0, 80), ':ip' => ip_visitante(), ':s' => $sucesso ? 1 : 0]
    );
    // higiene: mantém apenas 30 dias
    executa('DELETE FROM tentativas_login WHERE quando < :d', [':d' => gmdate('Y-m-d\TH:i:s\Z', time() - 2592000)]);
}

function autenticar(string $usuario, string $senha): array
{
    $usuario = trim($usuario);
    if ($usuario === '' || $senha === '') {
        usuario_ou_falso($senha); // consome tempo sem revelar existência
        return ['ok' => false, 'erro' => 'Informe usuário e senha.'];
    }
    if (limite_login_excedido($usuario)) {
        return ['ok' => false, 'erro' => 'Muitas tentativas. Tente novamente em alguns minutos.'];
    }
    $u = um('SELECT * FROM usuarios WHERE usuario = :u AND ativo = 1', [':u' => $usuario]);
    $v = $u ? senha_verificar($senha, (string) $u['senha_hash']) : ['ok'=>false,'rehash'=>null,'algo'=>'nenhum'];
    if (!$u || !$v['ok']) {
        registrar_tentativa($usuario, false);
        auditar('login_falha', 'usuarios', $u['id'] ?? null, 'usuario=' . $usuario);
        return ['ok' => false, 'erro' => 'Credenciais inválidas.'];
    }
    if ($v['rehash'] !== null) {                       // migra legado -> pbkdf2-sha256
        executa('UPDATE usuarios SET senha_hash = :h WHERE id = :id', [
            ':h' => $v['rehash'], ':id' => $u['id'],
        ]);
        auditar('senha_migrada', 'usuarios', (int) $u['id'], $v['algo']);
    }
    registrar_tentativa($usuario, true);
    abrir_sessao((int) $u['id']);
    auditar('login_ok', 'usuarios', (int) $u['id'], 'papel=' . $u['papel']);
    return ['ok' => true, 'usuario' => $u];
}

function usuario_ou_falso(string $senha): bool
{
    // Deriva um hash descartável: iguala o tempo de resposta quando o usuário não existe.
    senha_gerar($senha);
    return false;
}

function abrir_sessao(int $usuarioId): void
{
    in3_sessao_inicia();
    // uma sessão ativa por usuário: as anteriores são descartadas
    executa('DELETE FROM sessoes WHERE usuario_id = :u', [':u' => $usuarioId]);
    $token = bin2hex(random_bytes(32));
    executa(
        'INSERT INTO sessoes (token, usuario_id, criada_em, expira_em, ip, agente, csrf)
         VALUES (:t, :u, :c, :e, :ip, :ag, :csrf)',
        [
            ':t'    => $token,
            ':u'    => $usuarioId,
            ':c'    => agora(),
            ':e'    => gmdate('Y-m-d\TH:i:s\Z', time() + (int) (cfg('IN3_SESSAO_SEGUNDOS', '7200'))),
            ':ip'   => ip_visitante(),
            ':ag'   => agente(),
            ':csrf' => bin2hex(random_bytes(32)),
        ]
    );
    session_regenerate_id(true);
    $_SESSION['sessao'] = $token;
    executa('UPDATE usuarios SET ultimo_acesso = :q WHERE id = :id', [':q' => agora(), ':id' => $usuarioId]);
}

function encerrar_sessao(): void
{
    in3_sessao_inicia();
    $token = $_SESSION['sessao'] ?? null;
    if (is_string($token)) {
        executa('DELETE FROM sessoes WHERE token = :t', [':t' => $token]);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', time() - 42000, '/');
    }
    session_destroy();
}

function exigir_papel(string ...$papeis): array
{
    $u = usuario_atual();
    if ($u === null) {
        redirecionar(url_painel('/entrar?erro=sessao'));
    }
    if ($papeis && !in_array($u['papel'], $papeis, true)) {
        http_response_code(403);
        cabecalhos_seguranca(false);
        echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>403 · IN³</title>'
           . '<body style="font:16px/1.6 system-ui;background:#F6FFFC;color:#122A2E;padding:3rem">'
           . '<h1>403 · Acesso restrito</h1><p>Seu perfil não tem permissão para esta área.</p>'
           . '<p><a href="' . e(url_painel('/painel')) . '" style="color:#167D6E">Voltar ao painel</a></p></body></html>';
        exit;
    }
    return $u;
}

/* ---------------------------------------------------------- primeiro admin */

function existe_admin(): bool
{
    try {
        return (int) escalar("SELECT count(*) FROM usuarios WHERE papel = 'admin'") > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function criar_usuario(string $usuario, string $senha, string $papel = 'leitor', int $clareza = 1, string $nome = '', string $email = ''): int
{
    $usuario = trim($usuario);
    if (!preg_match('/^[a-zA-Z0-9._-]{3,40}$/', $usuario)) {
        throw new InvalidArgumentException('Usuário deve ter 3–40 caracteres: letras, números, ponto, hífen ou sublinhado.');
    }
    if (mb_strlen($senha) < 10) {
        throw new InvalidArgumentException('A senha deve ter ao menos 10 caracteres.');
    }
    if (!in_array($papel, ['admin', 'curador', 'leitor'], true)) {
        throw new InvalidArgumentException('Papel inválido.');
    }
    executa(
        'INSERT INTO usuarios (usuario, nome, email, papel, clareza, senha_hash, ativo, criado_em)
         VALUES (:u, :n, :m, :p, :c, :h, 1, :q)',
        [
            ':u' => $usuario,
            ':n' => texto_limpo($nome, 120),
            ':m' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
            ':p' => $papel,
            ':c' => max(1, min(4, $clareza)),
            ':h' => senha_gerar($senha),
            ':q' => agora(),
        ]
    );
    $id = ultimo_id();
    auditar('usuario_criado', 'usuarios', $id, 'papel=' . $papel . ' clareza=' . $clareza);
    return $id;
}

/* ------------------------------------------------- liberação por token */

function emitir_acesso(int $projetoId, int $clareza, ?int $pedidoId = null, int $dias = 30): string
{
    $token = bin2hex(random_bytes(24));
    executa(
        'INSERT INTO acessos (token, pedido_id, projeto_id, clareza, criado_em, expira_em, max_usos)
         VALUES (:t, :p, :pr, :c, :q, :e, :m)',
        [
            ':t'  => $token,
            ':p'  => $pedidoId,
            ':pr' => $projetoId,
            ':c'  => max(1, min(4, $clareza)),
            ':q'  => agora(),
            ':e'  => gmdate('Y-m-d\TH:i:s\Z', time() + $dias * 86400),
            ':m'  => (int) cfg('IN3_ACESSO_MAX_USOS', '12'),
        ]
    );
    auditar('acesso_emitido', 'acessos', $projetoId, 'clareza=' . $clareza . ' pedido=' . (string) $pedidoId);
    return $token;
}

/** Consome um token de acesso e eleva a clareza da sessão para o projeto. */
function usar_acesso(string $token): ?array
{
    $a = um(
        "SELECT * FROM acessos WHERE token = :t AND estado = 'ativo' AND expira_em > :q",
        [':t' => $token, ':q' => agora()]
    );
    if (!$a) {
        return null;
    }
    if ((int) $a['max_usos'] > 0 && (int) $a['usos'] >= (int) $a['max_usos']) {
        executa("UPDATE acessos SET estado = 'expirado' WHERE token = :t", [':t' => $token]);
        return null;
    }
    executa(
        "UPDATE acessos SET usa_ate = :q, usos = usos + 1 WHERE token = :t",
        [':q' => agora(), ':t' => $token]
    );
    in3_sessao_inicia();
    $lista = $_SESSION['acessos'] ?? [];
    if (!is_array($lista)) {
        $lista = [];
    }
    $lista[] = $token;
    $_SESSION['acessos'] = array_values(array_unique($lista));
    auditar('acesso_usado', 'acessos', (int) ($a['projeto_id'] ?? 0), 'clareza=' . $a['clareza']);
    return $a;
}

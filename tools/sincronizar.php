<?php
declare(strict_types=1);
/**
 * IN³ · sincronizar o inventário GitHub (server-side).
 *
 * Uso:
 *   php tools/sincronizar.php [--conta LACibermedicina] [--token $GITHUB_TOKEN] [--sem-commits]
 *
 * O token é lido de GITHUB_TOKEN (ambiente/.env), usado apenas nas chamadas e
 * NUNCA gravado em banco, arquivo, HTML ou log. Sem token, a API pública é
 * consultada e o que não puder ser confirmado fica marcado como "pendente" —
 * nada é adivinhado.
 */

require_once dirname(__DIR__) . '/src/nucleo.php';

$args = $argv;
$conta = cfg('IN3_CONTA', 'LACibermedicina');
$token = cfg('GITHUB_TOKEN', '') ?? '';
$semCommits = false;
foreach ($args as $i => $a) {
    if ($a === '--conta' && isset($args[$i + 1])) {
        $conta = $args[$i + 1];
    }
    if ($a === '--token' && isset($args[$i + 1])) {
        $token = $args[$i + 1];
    }
    if ($a === '--sem-commits') {
        $semCommits = true;
    }
}

$cab = ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'in3-incubator (in.m3d.pro)'];
if ($token !== '') {
    $cab['Authorization'] = 'Bearer ' . $token;
}

function gh(string $url, array $cab, int $tentativas = 3)
{
    for ($i = 1; $i <= $tentativas; $i++) {
        $ctx = stream_context_create(['http' => [
            'method' => 'GET', 'header' => implode("\r\n", array_map(
                static fn($k, $v) => $k . ': ' . $v, array_keys($cab), array_values($cab)
            )), 'timeout' => 25, 'ignore_errors' => true,
        ]]);
        $corpo = @file_get_contents($url, false, $ctx);
        $estado = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $estado = (int) $m[1];
        }
        if ($corpo !== false && $estado >= 200 && $estado < 300) {
            $d = json_decode($corpo, true);
            return $d;
        }
        if ($estado === 403 || $estado === 429) {
            sleep($i * 2);
            continue;
        }
        if ($estado === 404) {
            return null;
        }
        usleep(400000);
    }
    return null;
}

fwrite(STDOUT, "IN³ · sincronização GitHub — conta: {$conta} · token: " . ($token !== '' ? 'presente (não será gravado)' : 'ausente (somente público)') . "\n");

$repos = gh("https://api.github.com/users/{$conta}/repos?per_page=100&sort=pushed", $cab, 2);
if (!is_array($repos)) {
    // a segunda tentativa tenta o endpoint autenticado do usuário
    $repos = gh('https://api.github.com/user/repos?per_page=100&affiliation=owner', $cab, 1) ?: [];
}
if (!$repos) {
    fwrite(STDERR, "✖ A API do GitHub não devolveu repositórios. Verifique rede, conta ou token.\n");
    exit(2);
}

$readmes = [];
$commits = [];
$dir = IN3_DADOS . '/semear';
if (!is_dir($dir)) {
    mkdir($dir, 0770, true);
}
foreach ($repos as $idx => $r) {
    $nome = (string) ($r['name'] ?? '');
    if ($nome === '') {
        continue;
    }
    $ramo = (string) ($r['default_branch'] ?? 'main');
    if (($r['size'] ?? 0) < 40000) {
        $rd = gh("https://api.github.com/repos/{$conta}/{$nome}/readme?ref=" . rawurlencode($ramo), $cab, 1);
        if (is_array($rd) && !empty($rd['content'])) {
            $txt = base64_decode(str_replace(["\n", "\r"], '', (string) $rd['content']), true);
            $readmes[$nome] = $txt === false ? null : mb_substr((string) $txt, 0, 6000);
        }
    }
    if (!$semCommits) {
        $cm = gh("https://api.github.com/repos/{$conta}/{$nome}/commits?per_page=10", $cab, 1);
        if (is_array($cm)) {
            $commits[$nome] = array_values(array_filter(array_map(static function ($c) {
                if (!is_array($c)) {
                    return null;
                }
                return [
                    'sha' => substr((string) ($c['sha'] ?? ''), 0, 8),
                    'date' => (string) ($c['commit']['author']['date'] ?? ''),
                    'msg' => texto_limpo((string) ($c['commit']['message'] ?? ''), 200),
                    'author' => (string) ($c['commit']['author']['name'] ?? ($c['author']['login'] ?? '')),
                ];
            }, $cm)));
        }
    }
    if ($idx % 5 === 4) {
        fwrite(STDOUT, '  … ' . ($idx + 1) . '/' . count($repos) . " repositórios\n");
        usleep(300000);
    }
}

file_put_contents($dir . '/github.repos.cache.json', json_encode($repos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($dir . '/github.readmes.cache.json', json_encode($readmes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($dir . '/github.commits.cache.json', json_encode($commits, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$privados = 0;
foreach ($repos as $r) {
    if (!empty($r['private'])) {
        $privados++;
    }
}

fwrite(STDOUT, '  ✓ ' . count($repos) . " repositório(s) coletado(s) · {$privados} privado(s)\n");
if ($token === '') {
    fwrite(STDOUT, "  ! sem token: repositórios privados não são visíveis na API pública.\n"
        . "    Itens do acervo não confirmados permanecem com visibilidade 'pendente'.\n");
}
fwrite(STDOUT, "  → agora rode: php tools/semear.php && php tools/verificar.php\n");

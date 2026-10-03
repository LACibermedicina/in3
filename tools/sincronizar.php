<?php
declare(strict_types=1);
/**
 * IN³ · sincronizar.php — inventaria os repositórios da conta no GitHub.
 * O token (se houver) é usado só nesta chamada e nunca é gravado.
 *   php tools/sincronizar.php --conta=LACibermedicina [--token=ghp_xxx]
 */
require_once dirname(__DIR__) . '/src/nucleo.php';

$op = [];
foreach ($argv as $a) {
    if (preg_match('/^--([a-z_]+)(?:=(.*))?$/i', $a, $m)) {
        $op[$m[1]] = $m[2] ?? true;
    }
}
$conta = (string) ($op['conta'] ?? cfg('GITHUB_USER', 'LACibermedicina'));
$token = (string) ($op['token'] ?? cfg('GITHUB_TOKEN', ''));

fwrite(STDOUT, "\n=== IN³ · sincronização GitHub — conta: {$conta} · token: "
    . ($token !== '' ? 'presente (somente nesta chamada)' : 'ausente (somente público)') . " ===\n\n");

if (!banco_existe()) {
    fwrite(STDERR, "  ! banco ausente — rode primeiro: php tools/instalar.php\n");
    exit(1);
}

$pagina = 1;
$total = 0;
$publicos = 0;
$privados = 0;
$lista = [];

do {
    $url = 'https://api.github.com/users/' . rawurlencode($conta)
        . '/repos?per_page=100&sort=updated&type=all&page=' . $pagina;
    $cab = ['User-Agent: in3-incubator', 'Accept: application/vnd.github+json'];
    if ($token !== '') {
        $cab[] = 'Authorization: Bearer ' . $token;
    }
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => implode("\r\n", $cab),
        'timeout' => 25, 'ignore_errors' => true]]);
    $bruto = @file_get_contents($url, false, $ctx);
    if ($bruto === false) {
        fwrite(STDERR, "  ! falha de rede na página {$pagina}.\n");
        break;
    }
    $dados = json_decode($bruto, true);
    if (!is_array($dados) || $dados === []) {
        break;
    }
    foreach ($dados as $r) {
        if (!isset($r['name'])) {
            continue;
        }
        $lista[] = $r;
        $priv = !empty($r['private']);
        executar('INSERT INTO repos (nome, visibilidade, privado, linguagem, descricao, gh_atualizado_em, commits)
                  VALUES (:n,:v,:p,:l,:d,:q,0)
                  ON CONFLICT (nome) DO UPDATE SET visibilidade = excluded.visibilidade,
                    privado = excluded.privado, linguagem = excluded.linguagem, descricao = excluded.descricao,
                    gh_atualizado_em = excluded.gh_atualizado_em', [
            ':n' => (string) $r['name'], ':v' => $priv ? 'privado' : 'publico', ':p' => $priv ? 1 : 0,
            ':l' => (string) ($r['language'] ?? ''), ':d' => (string) ($r['description'] ?? ''),
            ':q' => (string) ($r['updated_at'] ?? ''),
        ]);
        $priv ? $privados++ : $publicos++;
        $total++;
    }
    if (count($dados) < 100) {
        break;
    }
    $pagina++;
} while ($pagina <= 3);

if ($token === '') {
    foreach (consulta("SELECT nome FROM repos WHERE visibilidade = 'pendente'") as $r) {
        // sem token o privado não é confirmado: permanece "pendente", nunca inventado
    }
}

gravar_config('sincronizado_em', agora());
gravar_config('github_conta', $conta);

// cache local para a semeadura offline
if ($lista) {
    @file_put_contents(IN3_RAIZ . '/data/semear/github.repos.cache.json',
        json_encode($lista, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

fwrite(STDOUT, '  ✓ ' . $total . ' repositório(s) coletado(s) · ' . $publicos . ' público(s) · ' . $privados . " privado(s)\n");
fwrite(STDOUT, '  ✓ repositórios no banco: ' . (int) escalar('SELECT count(*) FROM repos') . "\n");
if ($token === '') {
    fwrite(STDOUT, "  ! sem token: a API pública não devolve repositórios privados.\n"
        . "    Itens não confirmados ficam com visibilidade 'pendente' — nenhum nome é inventado.\n");
}
fwrite(STDOUT, "  → agora rode: php tools/semear.php && php tools/verificar.php\n");

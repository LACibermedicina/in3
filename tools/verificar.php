<?php
declare(strict_types=1);
/**
 * IN³ · verificar.php — prova, não promessa.
 * Reprovado (exit 1) = não publique.
 *   php tools/verificar.php
 */
require_once dirname(__DIR__) . '/src/nucleo.php';
require_once IN3_RAIZ . '/src/vistas.php';

$ok = [];
$falhas = [];

function checar(string $rotulo, bool $condicao, string $detalhe = ''): void
{
    global $ok, $falhas;
    if ($condicao) {
        $ok[] = $rotulo . ($detalhe !== '' ? " ({$detalhe})" : '');
        fwrite(STDOUT, '  ✔ ' . $rotulo . ($detalhe !== '' ? " ({$detalhe})" : '') . "\n");
    } else {
        $falhas[] = $rotulo;
        fwrite(STDOUT, '  ✖ ' . $rotulo . ($detalhe !== '' ? " ({$detalhe})" : '') . "\n");
    }
}

fwrite(STDOUT, "\n=== IN³ · verificação de privacidade ===\n\n");

if (!banco_existe()) {
    fwrite(STDERR, "  ✖ banco ausente — rode php tools/instalar.php\n");
    exit(1);
}

/* 1 · itens internos não aparecem na montagem pública */
$internos = consulta('SELECT * FROM projetos WHERE mostrar_ao_publico = 0');
$vazou = 0;
foreach ($internos as $p) {
    foreach (projetos_visiveis(4, false) as $v) {
        if ((int) $v['id'] === (int) $p['id']) {
            $vazou++;
        }
    }
}
checar('nenhum item interno na listagem pública', $vazou === 0, count($internos) . ' internos conferidos');

/* 2 · camada bloqueada não carrega conteúdo (o texto não sai do banco) */
$comConteudo = 0;
foreach (projetos_visiveis(4, false) as $p) {
    $m = camadas_do_projeto($p, 1, false);
    foreach ($m['camadas'] as $c) {
        if (!$c['liberada'] && trim((string) $c['corpo']) !== '') {
            $comConteudo++;
        }
    }
}
checar('camada bloqueada sem conteúdo servido ao anônimo', $comConteudo === 0, count(projetos_visiveis(4, false)) . ' projetos');

/* 3 · API pública: profundidade e itens internos */
$apiProj = [];
foreach (projetos_visiveis(1, false) as $p) {
    $m = camadas_do_projeto($p, 1, false);
    foreach ($m['camadas'] as $c) {
        if ($c['liberada'] && (int) $c['profundidade'] > 1) {
            $apiProj[] = $p['slug'];
        }
    }
}
checar('API pública nunca passa da camada 1 para anônimo', $apiProj === [], $apiProj ? implode(',', $apiProj) : 'ok');

/* 4 · busca: termo sensível não alcança camada > clareza */
$sensiveis = ['LGPD', 'risco', 'privacidade', 'arquitetura', 'integrações', 'playbook', 'notas internas'];
$viol = 0;
foreach ($sensiveis as $t) {
    foreach (buscar($t, 1, false)['resultados'] as $r) {
        if ((int) $r['profundidade'] > 1) {
            $viol++;
        }
    }
}
checar('busca anônima presa à camada 1', $viol === 0, count($sensiveis) . ' termos sensíveis');

/* 5 · a rota do painel não aparece no HTML público nem no robots.txt */
$home = vista_inicio([
    'projetos' => projetos_visiveis(1, false),
    'feed' => atualizacoes_recentes(1, 6),
    'tecnologias' => [],
    'n_publicos' => count(projetos_visiveis(1, false)),
    'n_tec' => 0,
    'cob' => cobertura_repos(),
]);
$temRota = str_contains(mb_strtolower($home), mb_strtolower(rota_painel()));
$robots = is_file(IN3_RAIZ . '/public/robots.txt') ? (string) file_get_contents(IN3_RAIZ . '/public/robots.txt') : '';
checar('rota do painel ausente do HTML público', !$temRota);
checar('rota do painel ausente do robots.txt', !str_contains(mb_strtolower($robots), mb_strtolower(rota_painel())));

/* 6 · nenhum endereço de repositório no HTML público enquanto desmarcado */
$links = 0;
foreach (projetos_visiveis(4, false) as $p) {
    if ((int) $p['mostrar_link_repo'] === 0 && str_contains($home, 'github.com/')) {
        $links++;
    }
}
checar('nenhuma URL de repositório no HTML público', $links === 0);

/* 7 · credenciais: só hash no banco, nenhum segredo em arquivo versionado */
$semHash = (int) escalar("SELECT count(*) FROM usuarios WHERE hash NOT LIKE '\$2y\$%' AND hash NOT LIKE '\$2a\$%'");
checar('todas as senhas gravadas como hash bcrypt', $semHash === 0, (int) escalar('SELECT count(*) FROM usuarios') . ' usuários');

$suspeitos = [];
$varre = array_values(array_filter(['src', 'tools', 'public', 'views', 'docs'],
    static fn(string $d): bool => is_dir(IN3_RAIZ . '/' . $d)));
foreach ($varre as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(IN3_RAIZ . '/' . $dir,
        FilesystemIterator::SKIP_DOTS));
    foreach ($it as $arq) {
        if (!$arq->isFile() || in_array($arq->getExtension(), ['db', 'png', 'jpg', 'zip'], true)) {
            continue;
        }
        $c = (string) @file_get_contents((string) $arq);
        if (preg_match('/ghp_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|sk-[A-Za-z0-9]{20,}|BEGIN (RSA|OPENSSH|EC) PRIVATE KEY/', $c)) {
            $suspeitos[] = str_replace(IN3_RAIZ . '/', '', (string) $arq);
        }
    }
}
checar('nenhum token ou chave privada em arquivo do projeto', $suspeitos === [], $suspeitos ? implode(', ', $suspeitos) : 'ok');

/* 8 · cobertura do acervo */
$cob = cobertura_repos();
checar('cobertura de repositórios', $cob['total'] > 0, $cob['mapeados'] . '/' . $cob['total']);

/* 9 · todas as camadas indexadas */
$camadas = (int) escalar('SELECT count(*) FROM camadas');
$esperado = (int) escalar('SELECT count(*) FROM projetos') * 4;
checar('quatro camadas por projeto', $camadas >= $esperado, $camadas . ' camadas para ' . (int) escalar('SELECT count(*) FROM projetos') . ' projetos');

fwrite(STDOUT, "\n");
if ($falhas) {
    fwrite(STDOUT, '  ' . count($falhas) . " FALHA(S) — não publique.\n\n");
    exit(1);
}
fwrite(STDOUT, '  Tudo certo: ' . count($ok) . " verificação(ões) aprovada(s).\n\n");
exit(0);

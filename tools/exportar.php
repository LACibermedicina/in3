<?php
declare(strict_types=1);
/**
 * IN³ · exportar.php — gera uma versão estática do site público.
 * Exporta sempre com clareza 1 (visitante anônimo): nada interno sai.
 *   php tools/exportar.php [--destino=public/estatico]
 */
require_once dirname(__DIR__) . '/src/nucleo.php';
require_once IN3_RAIZ . '/src/vistas.php';
require_once IN3_RAIZ . '/src/rotas_publicas.php';

$op = [];
foreach ($argv as $a) {
    if (preg_match('/^--([a-z_]+)(?:=(.*))?$/i', $a, $m)) {
        $op[$m[1]] = $m[2] ?? true;
    }
}
$destino = IN3_RAIZ . '/' . ltrim((string) ($op['destino'] ?? 'public/estatico'), '/');
@mkdir($destino . '/p', 0775, true);
@mkdir($destino . '/assets', 0775, true);

$origemAssets = IN3_RAIZ . '/public/assets';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($origemAssets, FilesystemIterator::SKIP_DOTS));
foreach ($it as $arq) {
    if (!$arq->isFile()) {
        continue;
    }
    $rel = substr((string) $arq, strlen($origemAssets) + 1);
    @mkdir(dirname($destino . '/assets/' . $rel), 0775, true);
    copy((string) $arq, $destino . '/assets/' . $rel);
}
$css = (string) @file_get_contents($origemAssets . '/in3.css');
$js  = (string) @file_get_contents($origemAssets . '/in3.js');

$inline = static function (string $html) use ($css, $js): string {
    $html = str_replace('<link rel="stylesheet" href="/assets/in3.css">', '<style>' . $css . '</style>', $html);
    return str_replace('<script src="/assets/in3.js" defer></script>', '<script>' . $js . '</script>', $html);
};

$projetos = projetos_visiveis(1, false);
$tec = tecnologias_publicas($projetos);

$home = layout('Portfólio vivo da incubadora', vista_inicio([
    'projetos' => $projetos, 'feed' => atualizacoes_recentes(1, 6), 'tecnologias' => $tec,
    'n_publicos' => count($projetos), 'n_tec' => count($tec), 'cob' => cobertura_repos(),
]));
file_put_contents($destino . '/index.html', $inline($home));

$n = 1;
foreach ($projetos as $p) {
    $m = camadas_do_projeto($p, 1, false);
    $rel = array_slice(array_values(array_filter($projetos, static fn(array $x): bool => (int) $x['id'] !== (int) $p['id'])), 0, 3);
    $html = layout((string) $p['titulo'], vista_projeto($p, $m, [], $rel, 1, false));
    file_put_contents($destino . '/p/' . $p['slug'] . '.html', $inline($html));
    $n++;
}
file_put_contents($destino . '/robots.txt', "User-agent: *\nDisallow: /\n");
file_put_contents($destino . '/LEIA-ME.txt', "Exportação estática do site público IN³ (clareza 1, visitante anônimo).\n"
    . "Gerada em " . agora() . ". Nenhum item interno foi exportado.\n");

fwrite(STDOUT, "\n=== IN³ · exportação estática ===\n\n");
fwrite(STDOUT, '  ✓ ' . $n . " arquivo(s) em " . str_replace(IN3_RAIZ . '/', '', $destino) . "\n");
fwrite(STDOUT, "  ✓ clareza 1 (visitante anônimo) — nada interno foi exportado\n\n");

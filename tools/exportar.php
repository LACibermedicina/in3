<?php
declare(strict_types=1);
/**
 * IN³ · exportação estática — gera um instantâneo autocontido do site público.
 *
 * Uso: php tools/exportar.php [--destino=public/estatico]
 *
 * Por que existe: o site público é renderizado por PHP por causa do filtro de
 * camadas. Mas há dois usos legítimos para uma cópia estática e honesta:
 *   · hospedagem simples (um HTML autocontido, sem backend);
 *   · comprovação visual do que o público realmente vê.
 *
 * Segurança: a exportação passa pela MESMA montagem de camadas, com clareza de
 * visitante anônimo (1). Item interno e camada bloqueada não entram no arquivo.
 */

require_once dirname(__DIR__) . '/src/nucleo.php';

$destino = IN3_PUBLICO . '/estatico';
foreach ($argv as $a) {
    if (str_starts_with($a, '--destino=')) {
        $destino = rtrim(substr($a, 10), '/');
    }
}

$clareza = 1; // visitante anônimo: a exportação nunca vê mais do que o público

/* ------------------------------------------------------- ativos embutidos */
$css = '';
foreach ([IN3_PUBLICO . '/assets/css/in3.css'] as $f) {
    $css .= (string) file_get_contents($f) . "\n";
}
$jsSite = (string) file_get_contents(IN3_PUBLICO . '/assets/js/site.js');
$jsVoxel = (string) file_get_contents(IN3_PUBLICO . '/assets/js/voxel.js');
$three = (string) file_get_contents(IN3_PUBLICO . '/assets/vendor/three.min.js');

/**
 * Torna o HTML autocontido: CSS/JS embutidos, links reescritos para .html.
 * Nada de token, nada de rota administrativa, nada de chamada de rede.
 */
function autocontido(string $html, string $css, string $jsSite, string $jsVoxel, string $three): string
{
    $html = preg_replace(
        '#<link rel="stylesheet" href="[^"]*in3\.css">#',
        '<style>' . $css . '</style>',
        $html
    ) ?? $html;
    $html = preg_replace(
        '#<script src="[^"]*three\.min\.js" defer></script>#',
        '<script>' . $three . '</script>',
        $html
    ) ?? $html;
    $html = preg_replace('#<script src="[^"]*voxel\.js" defer></script>#', '<script>' . $jsVoxel . '</script>', $html) ?? $html;
    $html = preg_replace('#<script src="[^"]*site\.js" defer></script>#', '<script>' . $jsSite . '</script>', $html) ?? $html;

    // rotas → arquivos
    $html = str_replace('href="/p/', 'href="p/', $html);
    $html = preg_replace('#href="/([a-z0-9\-]*)("|/)#', 'href="$1.html$2', $html) ?? $html;
    $html = str_replace('action="/pedido"', 'action="pedido.html"', $html);
    $html = str_replace(['href=".html"', 'href="/"'], ['href="index.html"', 'href="index.html"'], $html);
    $html = str_replace('href="busca.html?', 'href="busca.html?', $html);
    // a busca estática não executa PHP: avisa o visitante com honestidade
    $html = str_replace(
        '<p class="miudo">A consulta roda no servidor já filtrada:',
        '<p class="miudo"><strong>Instantâneo estático:</strong> neste arquivo a busca está desativada. '
        . 'A consulta roda no servidor já filtrada:',
        $html
    );
    return $html;
}

function grava(string $caminho, string $conteudo): void
{
    $dir = dirname($caminho);
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    file_put_contents($caminho, $conteudo);
}

$gerados = [];

/* ------------------------------------------------------------- página raiz */
$projetos = projetos_visiveis($clareza);
$cob = cobertura_repos();
$visPub = 0;
foreach ($cob['visibilidade'] as $v) {
    if ($v['visibilidade'] === 'publico') {
        $visPub = (int) $v['n'];
    }
}
$stats = [
    'projetos' => count($projetos), 'repos' => $cob['total'], 'repos_publicos' => $visPub,
    'categorias' => count(categorias_publicas()), 'atualizado' => (string) configuracao('sincronizado_em', ''),
];
$html = layout_publico(
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
grava($destino . '/index.html', autocontido($html, $css, $jsSite, $jsVoxel, $three));
$gerados[] = $destino . '/index.html';

/* --------------------------------------------------------- páginas simples */
$busca = layout_publico('Busca · IN³', 'Busca no portfólio vivo da IN³.',
    view('publico_busca.php', ['resultado' => null, 'termo' => '', 'clareza' => $clareza,
        'categorias' => categorias_publicas(), 'filtro' => []]),
    ['ativo' => 'busca', 'clareza' => $clareza, 'json' => ['tipo' => 'busca']]);
grava($destino . '/busca.html', autocontido($busca, $css, $jsSite, $jsVoxel, $three));
$gerados[] = $destino . '/busca.html';

$tec = layout_publico('Tecnologias · IN³', 'Tecnologias presentes nos projetos da incubadora IN³.',
    view('publico_tecnologias.php', ['tecnologias' => api_tecnologias($clareza, false)['tecnologias'], 'clareza' => $clareza]),
    ['ativo' => 'tecnologias', 'clareza' => $clareza]);
grava($destino . '/tecnologias.html', autocontido($tec, $css, $jsSite, $jsVoxel, $three));
$gerados[] = $destino . '/tecnologias.html';

$ped = layout_publico('Pedir detalhamento · IN³', 'Peça acesso ao detalhamento de um projeto da IN³.',
    view('publico_pedido.php', ['projetos' => $projetos, 'csrf' => '', 'erros' => [], 'clareza' => $clareza]),
    ['ativo' => 'pedido', 'clareza' => $clareza]);
grava($destino . '/pedido.html', autocontido($ped, $css, $jsSite, $jsVoxel, $three));
$gerados[] = $destino . '/pedido.html';

/* ------------------------------------------------------------ hotsite por projeto */
foreach ($projetos as $p) {
    $p['_repos'] = projeto_repos((int) $p['id']);
    $p['_tech'] = projeto_tech((int) $p['id']);
    $p['_marcos'] = projeto_marcos((int) $p['id']);
    $montagem = secoes_projeto($p, $clareza);
    $h = layout_publico(
        $p['titulo'] . ' · IN³',
        campo_publicavel((string) $p['resumo']) ? texto_limpo((string) $p['resumo'], 160) : 'Projeto da incubadora IN³ (m3d.pro).',
        view('publico_projeto.php', [
            'p' => $p, 'montagem' => $montagem, 'clareza' => $clareza,
            'proximo' => proximo_nivel_bloqueado($montagem['nivel_projeto'], $clareza),
            'csrf' => '', 'voxel' => voxel_projeto($p),
            'relacionados' => array_slice(array_values(array_filter(
                $projetos, static fn($x) => $x['categoria'] === $p['categoria'] && $x['id'] !== $p['id']
            )), 0, 3),
        ]),
        ['ativo' => 'inicio', 'clareza' => $clareza, 'dados' => [],
         'json' => ['tipo' => 'projeto', 'voxel' => cena_voxel([$p])]]
    );
    grava($destino . '/p/' . $p['slug'] . '.html', autocontido($h, $css, $jsSite, $jsVoxel, $three));
    $gerados[] = $destino . '/p/' . $p['slug'] . '.html';
}

/* ---------------------------------------------------------------- relatório */
$total = 0;
foreach ($gerados as $g) {
    $total += (int) filesize($g);
}
fwrite(STDOUT, "IN³ · exportação estática\n");
foreach ($gerados as $g) {
    fwrite(STDOUT, '  ✓ ' . str_replace(IN3_RAIZ . '/', '', $g) . '  (' . round(filesize($g) / 1024) . " KB)\n");
}
fwrite(STDOUT, '  ' . count($gerados) . ' arquivo(s) · ' . round($total / 1048576, 2) . " MB\n");
fwrite(STDOUT, "  clareza usada: 1 (visitante anônimo) — nada interno foi exportado\n");

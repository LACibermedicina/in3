<?php
declare(strict_types=1);
/**
 * =====================================================================
 * IN³ · INcubator — controlador frontal (roteador único)
 * Site público em /  ·  painel em /ROTA (padrão: /console)
 * =====================================================================
 */

require_once dirname(__DIR__) . '/src/nucleo.php';

/* ------------------- arquivos estáticos (servidor embutido do PHP) ------ */
if (PHP_SAPI === 'cli-server') {
    $arq = __DIR__ . (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (is_file($arq)) {
        return false;
    }
}

/* ------------------------------- uso pela linha de comando -------------- */
if (PHP_SAPI === 'cli') {
    require IN3_RAIZ . '/src/vistas.php';
    if (in_array('--instalar', $argv, true)) {
        require IN3_RAIZ . '/src/instalador.php';
        instalador_cli($argv);
        exit(0);
    }
    fwrite(STDERR, "Uso: php public/index.php --instalar [--usuario=arcano --senha=SEGREDO]\n");
    exit(1);
}

require_once IN3_RAIZ . '/src/vistas.php';
require_once IN3_RAIZ . '/src/rotas_publicas.php';
require_once IN3_RAIZ . '/src/rotas_painel.php';

sessao_inicia();

$uri  = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$uri  = '/' . trim(rawurldecode($uri), '/');
$rota = rota_painel();
$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

/* ----------------------------------------------- cabeçalhos de segurança */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$instalado = banco_existe() && existe_admin();

/* ------------------------------------------------------ rota do painel */
if ($uri === '/' . $rota || str_starts_with($uri, '/' . $rota . '/')) {
    if (!$instalado) {
        $corpo = vista_instalar();
        echo layout('Instalação', $corpo, ['admin' => true]);
        exit;
    }
    rota_painel_despachar($uri, $rota, $metodo);
    exit;
}

/* ----------------------------------------- API pública (JSON, filtrada) */
if ($uri === '/api/v1/publico/portfolio') {
    $cl = clareza_efetiva();
    $projetos = [];
    foreach (projetos_visiveis($cl) as $p) {
        $m = camadas_do_projeto($p, $cl);
        $cam = [];
        foreach ($m['camadas'] as $c) {
            if (!$c['liberada']) {
                continue; // camada bloqueada nunca entra no JSON
            }
            $cam[] = ['nivel' => (int) $c['profundidade'], 'titulo' => $c['titulo'], 'texto' => (string) $c['corpo']];
        }
        $projetos[] = [
            'slug' => (string) $p['slug'], 'titulo' => (string) $p['titulo'],
            'categoria' => (string) $p['categoria'], 'status' => (string) $p['status'],
            'resumo' => (string) $p['resumo'], 'publico' => ((int) $p['mostrar_ao_publico'] === 1),
            'teto_divulgacao' => (int) $m['teto'], 'profundidade_liberada' => (int) $m['limite'],
            'camadas' => $cam,
            'repositorios' => (int) $p['mostrar_link_repo'] === 1
                ? array_map(static fn(array $r): string => (string) $r['nome'], repos_do_projeto((int) $p['id']))
                : [],
            'tecnologias' => tecnologias_do_projeto((int) $p['id']),
        ];
    }
    $tec = [];
    foreach ($projetos as $p) {
        foreach ($p['tecnologias'] as $t) {
            $tec[$t] = ($tec[$t] ?? 0) + 1;
        }
    }
    arsort($tec);
    $cob = cobertura_repos();
    responder_json([
        'gerado_em' => agora(),
        'clareza' => $cl,
        'contagens' => ['projetos' => count($projetos), 'tecnologias' => count($tec),
                        'repositorios_mapeados' => $cob['mapeados'], 'repositorios_total' => $cob['total']],
        'tecnologias' => $tec,
        'projetos' => $projetos,
    ]);
    exit;
}

/* ------------------------------------------------ páginas públicas / erro */
if (!$instalado) {
    echo layout('Instalação necessária', vista_instalar(), ['admin' => true]);
    exit;
}

rota_publica_despachar($uri, $metodo);

/* --------------------------------------------------------------- helpers */

function responder_json(array $d, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

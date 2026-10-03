<?php
declare(strict_types=1);
/**
 * =====================================================================
 * IN³ · INcubator — controlador frontal (roteador único)
 * Site público em /  ·  painel em /ROTA (padrão: /console)
 * v4: + i18n (IA em tempo real) · sugestões/Kanban · RBAC por projeto
 * =====================================================================
 */

require_once dirname(__DIR__) . '/src/nucleo.php';
require_once IN3_RAIZ . '/src/i18n.php';

/* --------------------------------------------------------------- helpers */
function responder_json(array $d, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

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
require_once IN3_RAIZ . '/src/vistas_v4.php';
require_once IN3_RAIZ . '/src/backlog.php';
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
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

/* ------------------------------------------- idioma por link ou por query */
if (isset($_GET['lang']) && idioma_valido((string) $_GET['lang'])) {
    definir_idioma((string) $_GET['lang']);
    $limpo = preg_replace('/([?&])lang=[^&]*/', '$1', (string) ($_SERVER['REQUEST_URI'] ?? '/')) ?? '/';
    $limpo = rtrim(str_replace(['?&', '&&', '?'], ['?', '&', ''], $limpo), '?&');
    header('Location: ' . ($limpo === '' ? '/' : $limpo), true, 302);
    exit;
}
if (preg_match('#^/idioma/([A-Za-z\-]{2,7})$#', $uri, $m)) {
    definir_idioma($m[1]);
    header('Location: ' . url('/'), true, 302);
    exit;
}

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

/* ------------------------------------ tradução por IA (conteúdo em tempo real) */
if ($uri === '/api/v1/i18n/traducao') {
    if ($metodo !== 'POST') {
        responder_json(['erro' => 'use POST'], 405);
        exit;
    }
    $corpo = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($corpo)) {
        $corpo = $_POST;
    }
    $destino = (string) ($corpo['destino'] ?? '');
    if (!idioma_valido($destino)) {
        responder_json(['erro' => 'idioma não suportado'], 400);
        exit;
    }
    $textos = array_slice((array) ($corpo['textos'] ?? []), 0, 400);
    sessao_inicia();
    $n = (int) ($_SESSION['i18n_n'] ?? 0);
    if ($n > 6000) {
        responder_json(['erro' => 'limite de tradução da sessão atingido'], 429);
        exit;
    }
    $r = i18n_traduzir(array_map('strval', $textos), $destino);
    $_SESSION['i18n_n'] = $n + count($textos);
    // O mapa indexa pelo texto de origem; o JS casa por chave achatada.
    $mapaFino = [];
    foreach ($r['mapa'] as $orig => $trad) {
        $mapaFino[(string) $orig] = (string) $trad;
    }
    try {
        executar('INSERT INTO i18n_uso (idioma, n_textos, fonte, quando) VALUES (:i,:n,:f,:q)', [
            ':i' => $destino, ':n' => count($textos), ':f' => (string) $r['fonte'], ':q' => agora(),
        ]);
    } catch (Throwable $e) {
    }
    responder_json([
        'ok' => true, 'idioma' => $destino, 'fonte' => $r['fonte'],
        'ia_ligada' => $r['ia_ligada'], 'n' => count($textos), 'mapa' => $mapaFino,
    ]);
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
        'idioma' => idioma_atual(),
        'idiomas' => array_keys(IN3_IDIOMAS),
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

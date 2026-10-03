<?php
declare(strict_types=1);
/**
 * =====================================================================
 * IN³ · i18n — multilíngue com tradução por IA em tempo real
 * Idiomas: pt-BR · en · es · gn (Avañe'ẽ / Guarani paraguaio) · zh (中文)
 *
 * Estratégia em três degraus (nunca inventa tradução):
 *   1) cache local   data/i18n/<idioma>.json  (mesmo texto nunca retraduz)
 *   2) IA plugável   POST IN3_IA_URL com {textos:[...], destino:'en'}
 *   3) dicionário    base de UI embutida (fallback offline, marcado)
 * Sem chave de IA e sem dicionário, devolve o próprio original.
 * =====================================================================
 */

const IN3_IDIOMAS = [
    'pt-BR' => ['nativo' => 'Português',   'rotulo' => 'Português (Brasil)', 'emoji' => '🇧🇷', 'sigla' => 'BR', 'ia' => 'pt-BR'],
    'en'    => ['nativo' => 'English',     'rotulo' => 'Inglês',             'emoji' => '🇺🇸', 'sigla' => 'US', 'ia' => 'en'],
    'es'    => ['nativo' => 'Español',     'rotulo' => 'Espanhol',           'emoji' => '🇪🇸', 'sigla' => 'ES', 'ia' => 'es'],
    'gn'    => ['nativo' => "Avañe'ẽ",     'rotulo' => 'Guarani',            'emoji' => '🇵🇾', 'sigla' => 'PY', 'ia' => 'gn'],
    'zh'    => ['nativo' => '中文',         'rotulo' => 'Chinês (Mandarim)',  'emoji' => '🇨🇳', 'sigla' => 'CN', 'ia' => 'zh-CN'],
];

function idioma_valido(?string $l): bool
{
    return is_string($l) && $l !== '' && array_key_exists($l, IN3_IDIOMAS);
}

function idioma_atual(): string
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    sessao_inicia();
    $l = $_SESSION['lang'] ?? ($_COOKIE['in3_lang'] ?? '');
    if (!idioma_valido($l)) {
        $l = cfg('IN3_IDIOMA_PADRAO', 'pt-BR');
    }
    return $cache = (idioma_valido($l) ? (string) $l : 'pt-BR');
}

function definir_idioma(string $l): bool
{
    if (!idioma_valido($l)) {
        return false;
    }
    sessao_inicia();
    $_SESSION['lang'] = $l;
    if (PHP_SAPI !== 'cli') {
        setcookie('in3_lang', $l, [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'samesite' => 'Lax',
            'httponly' => false,
        ]);
    }
    return true;
}

function idioma_rotulo(string $l): string
{
    return (string) (IN3_IDIOMAS[$l]['nativo'] ?? $l);
}

/* ------------------------------------------------------------ cache local */

function i18n_dir(): string
{
    $d = IN3_DATA . '/i18n';
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
    return $d;
}

function i18n_arquivo(string $idioma): string
{
    return i18n_dir() . '/' . preg_replace('/[^A-Za-z\-]/', '', $idioma) . '.json';
}

function i18n_cache_carregar(string $idioma): array
{
    static $mem = [];
    if (isset($mem[$idioma])) {
        return $mem[$idioma];
    }
    $arq = i18n_arquivo($idioma);
    $d = is_file($arq) ? json_decode((string) @file_get_contents($arq), true) : [];
    return $mem[$idioma] = (is_array($d) ? $d : []);
}

function i18n_cache_salvar(string $idioma, array $mapa): void
{
    $arq = i18n_arquivo($idioma);
    $atual = i18n_cache_carregar($idioma);
    @file_put_contents($arq, (string) json_encode($atual + $mapa, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function i18n_chave(string $texto): string
{
    return substr(sha1('in3|' . $texto), 0, 20);
}

/* ------------------------------------------------- dicionário de base (UI) */

function i18n_dicionario(string $idioma): array
{
    $d = [
        'pt-BR' => [],
        'en' => [
            'Portfólio' => 'Portfolio', 'Busca' => 'Search', 'Tecnologias' => 'Technologies',
            'Pedir detalhamento' => 'Request details', 'Enter' => 'Sign in', 'Sair' => 'Sign out',
            'Acesso restrito' => 'Restricted access', 'Não encontrado' => 'Not found',
            'Voltar ao portfólio' => 'Back to portfolio', 'camada' => 'layer',
            'São' => 'They are', 'Situação hoje' => 'Current status', 'ativo' => 'active',
            'em incubação' => 'incubating', 'protótipo' => 'prototype', 'entregue' => 'delivered',
            'pesquisa' => 'research', 'backlog' => 'backlog', 'arquivado' => 'archived',
            'infra' => 'infrastructure', 'Roadmap institucional' => 'Institutional roadmap',
            'Institucional' => 'Institutional', 'Técnico' => 'Technical', 'Playbook completo' => 'Full playbook',
            'Portfólio vivo da incubadora' => 'Living portfolio of the incubator',
            'O que este projeto é' => 'What this project is', 'Contexto institucional' => 'Institutional context',
            'Arquitetura e integrações' => 'Architecture and integrations',
            'Sugerir um projeto' => 'Suggest a project', 'Enviar sugestão' => 'Send suggestion',
            'Escolha o projeto' => 'Choose the project', 'Ver detalhes' => 'View details',
            'Traduzindo…' => 'Translating…', 'Idioma' => 'Language',
        ],
        'es' => [
            'Portfólio' => 'Portafolio', 'Busca' => 'Búsqueda', 'Tecnologias' => 'Tecnologías',
            'Pedir detalhamento' => 'Solicitar detalles', 'Enter' => 'Entrar', 'Sair' => 'Salir',
            'Acesso restrito' => 'Acceso restringido', 'Não encontrado' => 'No encontrado',
            'Voltar ao portfólio' => 'Volver al portafolio', 'camada' => 'capa',
            'Situação hoje' => 'Situación actual', 'ativo' => 'activo', 'em incubação' => 'en incubación',
            'protótipo' => 'prototipo', 'entregue' => 'entregado', 'pesquisa' => 'investigación',
            'arquivado' => 'archivado', 'infra' => 'infraestructura',
            'Roadmap institucional' => 'Hoja de ruta institucional', 'Institucional' => 'Institucional',
            'Técnico' => 'Técnico', 'Playbook completo' => 'Manual completo',
            'Portfólio vivo da incubadora' => 'Portafolio vivo de la incubadora',
            'O que este projeto é' => 'Qué es este proyecto', 'Contexto institucional' => 'Contexto institucional',
            'Sugerir um projeto' => 'Sugerir un proyecto', 'Enviar sugestão' => 'Enviar sugerencia',
            'Escolha o projeto' => 'Elija el proyecto', 'Ver detalhes' => 'Ver detalles',
            'Traduzindo…' => 'Traduciendo…', 'Idioma' => 'Idioma',
        ],
        'gn' => [
            'Portfólio' => 'Portafolio', 'Busca' => 'Jeheka', 'Tecnologias' => 'Teknología',
            'Pedir detalhamento' => 'Jerure mba\'ekuaa', 'Enter' => 'Jike', 'Sair' => 'Ñesẽ',
            'Acesso restrito' => 'Jikekuaa oñemboyke', 'Não encontrado' => 'Ndorekói',
            'Voltar ao portfólio' => 'Jevy portafolio-pe', 'camada' => 'peteĩ pehẽngue',
            'Situação hoy' => 'Ko\'ág̃a', 'ativo' => 'oikovéva', 'em incubação' => 'incubación-pe',
            'protótipo' => 'protótipo', 'entregue' => 'ome\'ẽma', 'pesquisa' => 'tembikuaaty rehegua',
            'Sugerir um projeto' => 'Ehapy peteĩ tembiapouka', 'Enviar sugestão' => 'Mondo',
            'Escolha o projeto' => 'Eiporavo tembiapouka', 'Ver detalhes' => 'Ehecha mba\'ekuaa',
            'Traduzindo…' => 'Oñembohasa…', 'Idioma' => 'Ñe\'ẽ',
            'Portfólio vivo da incubadora' => 'Incubadora portafolio oikovéva',
        ],
        'zh' => [
            'Portfólio' => '作品集', 'Busca' => '搜索', 'Tecnologias' => '技术',
            'Pedir detalhamento' => '申请详细信息', 'Enter' => '登录', 'Sair' => '退出',
            'Acesso restrito' => '受限访问', 'Não encontrado' => '未找到',
            'Voltar ao portfólio' => '返回作品集', 'camada' => '层级',
            'Situação hoje' => '当前状态', 'ativo' => '进行中', 'em incubação' => '孵化中',
            'protótipo' => '原型', 'entregue' => '已交付', 'pesquisa' => '研究',
            'arquivado' => '已归档', 'infra' => '基础设施',
            'Roadmap institucional' => '机构路线图', 'Institucional' => '机构', 'Técnico' => '技术',
            'Playbook completo' => '完整手册', 'Portfólio vivo da incubadora' => '孵化器动态作品集',
            'O que este projeto é' => '项目简介', 'Contexto institucional' => '机构背景',
            'Arquitetura e integrações' => '架构与集成', 'Sugerir um projeto' => '推荐项目',
            'Enviar sugestão' => '提交建议', 'Escolha o projeto' => '选择项目',
            'Ver detalhes' => '查看详情', 'Traduzindo…' => '正在翻译…', 'Idioma' => '语言',
        ],
    ];
    $base = $d['pt-BR'] ?? [];
    return $d[$idioma] ?? $base;
}

/* ------------------------------------------------------ IA plugável (LLM) */

function i18n_ia_url(): string
{
    return trim(cfg('IN3_IA_URL', ''));
}

function i18n_ia_chave(): string
{
    return trim(cfg('IN3_IA_CHAVE', ''));
}

function i18n_ia_ligada(): bool
{
    return i18n_ia_url() !== '' && i18n_ia_chave() !== '';
}

/**
 * Contrato do endpoint de IA (configurável em .env):
 *   POST {IN3_IA_URL}
 *   Authorization: Bearer {IN3_IA_CHAVE}
 *   { "modelo": "...", "origem": "pt-BR", "destino": "en",
 *     "instrucoes": "...", "textos": ["...", "..."] }
 *   → { "traducoes": ["...", "..."] }   (mesma ordem e mesmo tamanho)
 */
function i18n_ia_traduzir(array $textos, string $idioma): array
{
    if (!i18n_ia_ligada() || !$textos) {
        return [];
    }
    $destino = (string) (IN3_IDIOMAS[$idioma]['ia'] ?? $idioma);
    $corpo = [
        'modelo'     => cfg('IN3_IA_MODELO', 'gpt-4o-mini'),
        'origem'     => 'pt-BR',
        'destino'    => $destino,
        'instrucoes' => 'Traduza cada item para ' . $destino . '. Contexto: portfólio de incubadora de '
            . 'tecnologia em saúde (m3d.pro). Mantenha nomes próprios, siglas técnicas e nomes de repositório '
            . 'inalterados. Para guarani (gn): norma paraguaia, com empréstimo para termo técnico sem equivalente. '
            . 'Devolva SOMENTE JSON {"traducoes":[...]} com o mesmo número de itens e na mesma ordem.',
        'textos'     => array_values($textos),
    ];
    $ch = curl_init(i18n_ia_url());
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . i18n_ia_chave()],
        CURLOPT_POSTFIELDS     => (string) json_encode($corpo, JSON_UNESCAPED_UNICODE),
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false || $code < 200 || $code >= 300) {
        return [];
    }
    $d = json_decode((string) $resp, true);
    $t = $d['traducoes'] ?? ($d['data']['traducoes'] ?? null);
    if (!is_array($t) || count($t) !== count($textos)) {
        return [];
    }
    return array_values(array_map(static fn($v): string => is_string($v) ? $v : '', $t));
}

/* ------------------------------------------------------------- tradução */

/**
 * Traduz um lote de textos. Retorna ['pt-BR'=>..., 'mapa'=>[orig=>trad], 'fonte'=>...].
 */
function i18n_traduzir(array $textos, string $idioma): array
{
    $limpos = [];
    foreach ($textos as $t) {
        $t = (string) $t;
        if (trim($t) === '' || mb_strlen($t) > 2000) {
            continue;
        }
        $limpos[$t] = true;
    }
    $originais = array_keys($limpos);
    if (!$originais) {
        return ['idioma' => $idioma, 'mapa' => [], 'fonte' => 'vazio', 'ia_ligada' => i18n_ia_ligada()];
    }
    if ($idioma === 'pt-BR') {
        return ['idioma' => 'pt-BR', 'mapa' => array_combine($originais, $originais), 'fonte' => 'original', 'ia_ligada' => i18n_ia_ligada()];
    }

    $cache = i18n_cache_carregar($idioma);
    $dicio = i18n_dicionario($idioma);
    $mapa = [];
    $faltando = [];
    foreach ($originais as $o) {
        $k = i18n_chave($o);
        if (isset($cache[$k]) && $cache[$k] !== '') {
            $mapa[$o] = (string) $cache[$k];
        } elseif (isset($dicio[$o])) {
            $mapa[$o] = (string) $dicio[$o];
            $cache[$k] = (string) $dicio[$o];
        } else {
            $faltando[] = $o;
        }
    }

    $fonte = $mapa ? 'cache+dicionario' : 'vazio';
    if ($faltando) {
        $daIa = i18n_ia_traduzir($faltando, $idioma);
        if ($daIa) {
            $fonte = 'ia';
            foreach ($faltando as $i => $o) {
                $mapa[$o] = $daIa[$i];
                $cache[i18n_chave($o)] = $daIa[$i];
            }
        } else {
            $fonte = $mapa ? 'cache+dicionario' : 'sem-ia';
            foreach ($faltando as $o) {
                $mapa[$o] = $o; // honesto: sem tradução disponível, devolve original
            }
        }
    }
    i18n_cache_salvar($idioma, $cache);

    return ['idioma' => $idioma, 'mapa' => $mapa, 'fonte' => $fonte,
            'ia_ligada' => i18n_ia_ligada(), 'pendentes' => count($faltando)];
}

function i18n_estatisticas(): array
{
    $out = [];
    foreach (array_keys(IN3_IDIOMAS) as $l) {
        $out[$l] = count(i18n_cache_carregar($l));
    }
    return $out;
}

/* ------------------------------------------------------- barra de idiomas */

function i18n_barra_html(string $classe = 'idiomas'): string
{
    $atual = idioma_atual();
    $h = '<div class="' . e($classe) . '" role="group" aria-label="Idioma / Language">';
    foreach (IN3_IDIOMAS as $cod => $m) {
        $on = $cod === $atual;
        $h .= '<button type="button" class="bandeira' . ($on ? ' on' : '') . '" data-lang="' . e($cod) . '"'
            . ' aria-pressed="' . ($on ? 'true' : 'false') . '" title="' . e((string) $m['rotulo']) . '">'
            . '<span class="bz" aria-hidden="true">' . $m['emoji'] . '</span>'
            . '<span class="bt">' . e((string) $m['nativo']) . '</span></button>';
    }
    return $h . '</div>';
}

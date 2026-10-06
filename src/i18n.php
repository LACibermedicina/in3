<?php
declare(strict_types=1);
/**
 * IN³ · camada de idiomas.
 *
 * Cinco idiomas exatos:
 *   pt-BR 🇧🇷 · es 🇪🇸 · en 🇺🇸 · zh-CN 🇨🇳 · gn 🇵🇾 (Guarani)
 *
 * Duas velocidades de tradução:
 *   1) DICIONÁRIO estático em data/idiomas/<codigo>.json — sempre disponível,
 *      offline, sem chave de API. É a base da interface.
 *   2) MOTOR DE IA opcional — endpoint /api/traduzir, chave exclusivamente no
 *      servidor (.env), resultado em cache na tabela `traducoes`.
 *
 * Garantia de não vazamento: a chave de cache é o hash do TEXTO DE ORIGEM.
 * Um visitante só pode encomendar a tradução de um texto que já recebeu; como o
 * material interno nunca é montado no HTML público (camadas bloqueadas são
 * removidas no servidor), este cache não é um caminho de vazamento.
 */

const IN3_IDIOMAS = [
    'pt-BR' => ['bandeira' => '🇧🇷', 'rotulo' => 'Português do Brasil', 'nativo' => 'Português (BR)', 'locale' => 'pt_BR'],
    'es'    => ['bandeira' => '🇪🇸', 'rotulo' => 'Espanhol',             'nativo' => 'Español',        'locale' => 'es_ES'],
    'en'    => ['bandeira' => '🇺🇸', 'rotulo' => 'Inglês',               'nativo' => 'English',        'locale' => 'en_US'],
    'zh-CN' => ['bandeira' => '🇨🇳', 'rotulo' => 'Chinês',               'nativo' => '中文',            'locale' => 'zh_CN'],
    'gn'    => ['bandeira' => '🇵🇾', 'rotulo' => 'Guarani',              'nativo' => "Avañe'ẽ",        'locale' => 'gn_PY'],
];

function idiomas_disponiveis(): array
{
    return IN3_IDIOMAS;
}

function idioma_padrao(): string
{
    try {
        $p = (string) configuracao('idioma_padrao', 'pt-BR');
    } catch (Throwable $ex) {
        return 'pt-BR';           // durante a instalação ainda não há banco
    }
    return isset(IN3_IDIOMAS[$p]) ? $p : 'pt-BR';
}

function idioma_valido(string $codigo): bool
{
    return isset(IN3_IDIOMAS[$codigo]);
}

/** Idioma da requisição: cookie > sessão > padrão. Nunca vem do banco do visitante. */
function idioma_atual(): string
{
    if (PHP_SAPI === 'cli') {
        return idioma_padrao();
    }
    if (isset($_GET['idioma']) && is_string($_GET['idioma']) && idioma_valido($_GET['idioma'])) {
        return $_GET['idioma'];
    }
    if (isset($_COOKIE['in3_idioma']) && is_string($_COOKIE['in3_idioma']) && idioma_valido($_COOKIE['in3_idioma'])) {
        return $_COOKIE['in3_idioma'];
    }
    in3_sessao_inicia();
    $s = $_SESSION['idioma'] ?? null;
    return (is_string($s) && idioma_valido($s)) ? $s : idioma_padrao();
}

function idioma_definir(string $codigo): bool
{
    if (!idioma_valido($codigo)) {
        return false;
    }
    in3_sessao_inicia();
    $_SESSION['idioma'] = $codigo;
    if (!headers_sent()) {
        setcookie('in3_idioma', $codigo, [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
    return true;
}

/* ------------------------------------------------------------- dicionário */

function dicionario_arquivo(string $idioma): string
{
    return IN3_DADOS . '/idiomas/' . basename($idioma) . '.json';
}

function dicionario(string $idioma): array
{
    static $memo = [];
    if (isset($memo[$idioma])) {
        return $memo[$idioma];
    }
    $padrao = $idioma === 'pt-BR' ? [] : (function () {
        return dicionario('pt-BR');
    })();
    $arq = dicionario_arquivo($idioma);
    if (!is_file($arq)) {
        return $memo[$idioma] = $padrao;
    }
    $d = json_decode((string) file_get_contents($arq), true);
    if (!is_array($d)) {
        return $memo[$idioma] = $padrao;
    }
    // pt-BR é a fonte; os outros idiomas caem nele para chaves ausentes
    return $memo[$idioma] = ($idioma === 'pt-BR' ? $d : array_merge($padrao, $d));
}

/** Texto de interface no idioma atual. */
function t(string $chave, string $padrao = ''): string
{
    try {
        $d = dicionario(idioma_atual());
    } catch (Throwable $ex) {
        return $padrao !== '' ? $padrao : $chave;
    }
    if (isset($d['interface'][$chave])) {
        return (string) $d['interface'][$chave];
    }
    $pt = dicionario('pt-BR');
    return (string) ($pt['interface'][$chave] ?? ($padrao !== '' ? $padrao : $chave));
}

/* ------------------------------------------------------- motor de tradução */

function traducao_configurada(): bool
{
    $url = (string) (cfg('IN3_TRADUCAO_URL', '') ?? '');
    $key = (string) (cfg('IN3_TRADUCAO_CHAVE', '') ?? '');
    return $url !== '' && $key !== '';
}

function traducao_modelo(): string
{
    return (string) configuracao('traducao_modelo', (string) (cfg('IN3_TRADUCAO_MODELO', 'gpt-4o-mini') ?? 'gpt-4o-mini'));
}

function traducao_hash(string $texto): string
{
    return hash('sha256', $texto);
}

function traducao_cache(string $idioma, string $texto): ?string
{
    $l = um(
        'SELECT texto_traduzido FROM traducoes WHERE idioma = :i AND origem_hash = :h',
        [':i' => $idioma, ':h' => traducao_hash($texto)]
    );
    return $l ? (string) $l['texto_traduzido'] : null;
}

function traducao_gravar(string $idioma, string $original, string $traduzido, string $motor): void
{
    if ($original === '' || $traduzido === '' || $original === $traduzido) {
        return;
    }
    executa(
        'INSERT INTO traducoes (idioma, origem_hash, texto_original, texto_traduzido, motor, criado_em)
         VALUES (:i, :h, :o, :t, :m, :q)
         ON CONFLICT(idioma, origem_hash) DO UPDATE SET texto_traduzido = :t, motor = :m, criado_em = :q',
        [':i' => $idioma, ':h' => traducao_hash($original), ':o' => $original,
         ':t' => $traduzido, ':m' => $motor, ':q' => agora()]
    );
}

/** Chamada única ao provedor de IA. Recebe lote e devolve lista na mesma ordem. */
function traducao_ia(array $textos, string $idioma): ?array
{
    if (!traducao_configurada()) {
        return null;
    }
    $rotulo = IN3_IDIOMAS[$idioma]['rotulo'] ?? $idioma;
    $linhas = [];
    foreach ($textos as $i => $x) {
        $linhas[] = ($i + 1) . '. ' . str_replace(["\r", "\n"], ' ', (string) $x);
    }
    $prompt = "Traduza para {$rotulo} ({$idioma}) mantendo tom institucional e técnico preciso. "
        . "Devolva SOMENTE um JSON válido do tipo {\"1\":\"...\",\"2\":\"...\"} com a mesma numeração, "
        . "sem comentários e sem texto fora do JSON. Termos técnicos, marcas e siglas podem permanecer "
        . "em inglês quando forem nomes próprios.\n\n" . implode("\n", $linhas);

    $corpo = json_encode([
        'model'    => traducao_modelo(),
        'messages' => [
            ['role' => 'system', 'content' => 'Você é um tradutor institucional. Responde apenas JSON.'],
            ['role' => 'user',   'content' => $prompt],
        ],
        'temperature' => 0.2,
    ], JSON_UNESCAPED_UNICODE);

    $url   = (string) cfg('IN3_TRADUCAO_URL', '');
    $chave = (string) cfg('IN3_TRADUCAO_CHAVE', '');
    $html  = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $chave],
            CURLOPT_POSTFIELDS => $corpo,
        ]);
        $html = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$chave}\r\n",
            'content' => $corpo,
            'timeout' => 25,
            'ignore_errors' => true,
        ]]);
        $html = @file_get_contents($url, false, $ctx);
    }
    if (!is_string($html) || $html === '') {
        return null;
    }
    $j = json_decode($html, true);
    $txt = $j['choices'][0]['message']['content'] ?? null;
    if (!is_string($txt)) {
        return null;
    }
    $txt = trim(preg_replace('/^```(?:json)?|```$/m', '', $txt));
    $mapa = json_decode($txt, true);
    if (!is_array($mapa)) {
        return null;
    }
    $saida = [];
    foreach ($textos as $i => $x) {
        $v = $mapa[(string) ($i + 1)] ?? $mapa[$i] ?? null;
        $saida[] = (is_string($v) && $v !== '') ? $v : (string) $x;
    }
    return $saida;
}

/**
 * Traduz um lote de textos para um idioma.
 * Ordem de resolução: cache → dicionário da interface → motor de IA (se houver chave) → original.
 *
 * @return array{idioma:string,motor:string,textos:array<string,string>,traduzidos:int,cacheados:int}
 */
function traduzir_lote(array $textos, string $idioma, bool $usarIa = true): array
{
    if (!idioma_valido($idioma)) {
        $idioma = idioma_padrao();
    }
    $originais = [];
    foreach ($textos as $k => $v) {
        $v = trim((string) $v);
        if ($v !== '') {
            $originais[(string) $k] = $v;
        }
    }

    $saida = [];
    $pendentes = [];
    $cacheados = 0;

    $dict = dicionario($idioma)['interface'] ?? [];
    foreach ($originais as $k => $v) {
        $c = traducao_cache($idioma, $v);
        if ($c !== null) {
            $saida[$k] = $c;
            $cacheados++;
            continue;
        }
        if ($idioma === 'pt-BR') {
            $saida[$k] = $v;
            continue;
        }
        if (isset($dict[$v])) {                 // interface já traduzida estaticamente
            $saida[$k] = (string) $dict[$v];
            traducao_gravar($idioma, $v, (string) $dict[$v], 'dicionario');
            continue;
        }
        $pendentes[$k] = $v;
    }

    $motor = ($cacheados > 0) ? 'cache' : 'dicionario';
    if ($pendentes && $usarIa && traducao_configurada()) {
        $lista = array_values($pendentes);
        $r = traducao_ia($lista, $idioma);
        if (is_array($r)) {
            $i = 0;
            foreach ($pendentes as $k => $v) {
                $saida[$k] = (string) ($r[$i] ?? $v);
                traducao_gravar($idioma, $v, $saida[$k], 'ia');
                $i++;
            }
            $pendentes = [];
            $motor = 'ia';
        }
    }
    foreach ($pendentes as $k => $v) {
        $saida[$k] = $v;                        // sem chave de IA: mantém o original
    }
    if ($motor === 'dicionario' && $cacheados === 0 && !traducao_configurada()) {
        $motor = 'dicionario';
    }

    return [
        'idioma'      => $idioma,
        'motor'       => $motor,
        'textos'      => $saida,
        'traduzidos'  => count($saida),
        'cacheados'   => $cacheados,
        'ia_ligada'   => traducao_configurada(),
    ];
}

function traducao_estatisticas(): array
{
    $linhas = consulta('SELECT idioma, motor, count(*) AS n FROM traducoes GROUP BY idioma, motor ORDER BY idioma');
    return [
        'por_idioma' => $linhas,
        'total'      => (int) escalar('SELECT count(*) FROM traducoes'),
        'ia_ligada'  => traducao_configurada(),
        'modelo'     => traducao_modelo(),
    ];
}

/** Pacote de strings que o navegador usa para trocar a interface sem recarregar. */
function pacote_idioma(string $idioma): array
{
    $d = dicionario($idioma);
    return [
        'idioma'    => $idioma,
        'interface' => $d['interface'] ?? [],
        'idiomas'   => IN3_IDIOMAS,
        'ia'        => traducao_configurada(),
    ];
}


/**
 * POST /api/traduzir  { "idioma": "en", "textos": { "chave": "texto", ... } }
 * GET  /api/traduzir?idioma=en   -> pacote de strings da interface
 *
 * A chave da IA vive apenas no .env do servidor. O endpoint aceita somente
 * textos curtos e nunca lê o banco por conta própria: ele traduz o que o
 * cliente já possui, de modo que não existe caminho para obter conteúdo de
 * camada bloqueada por meio dele.
 */
function api_traduzir(): never
{
    $idioma = (string) entrada('idioma', idioma_atual());

    if (metodo() === 'GET') {
        json_saida(['api' => 'in3/v1', 'gerado_em' => agora()] + pacote_idioma($idioma));
    }

    $dados  = corpo_json() ?: $_POST;
    $idioma = (string) ($dados['idioma'] ?? $idioma);
    if (!idioma_valido($idioma)) {
        json_saida(['erro' => 'idioma não suportado', 'suportados' => array_keys(IN3_IDIOMAS)], 422);
    }
    $textos = $dados['textos'] ?? [];
    if (!is_array($textos) || !$textos) {
        json_saida(['erro' => 'informe "textos" como objeto chave -> texto'], 422);
    }
    if (count($textos) > 40) {
        json_saida(['erro' => 'máximo de 40 textos por requisição'], 413);
    }
    $limpos = [];
    foreach ($textos as $k => $v) {
        $v = (string) $v;
        if (mb_strlen($v) > 2000) {
            json_saida(['erro' => 'texto acima de 2000 caracteres'], 413);
        }
        $limpos[mb_substr((string) $k, 0, 80)] = $v;
    }
    $r = traduzir_lote($limpos, $idioma, (bool) (cfg('IN3_TRADUCAO_CHAVE', '') ?? ''));
    auditar('traducao', 'traducoes', null, 'idioma=' . $idioma . ' motor=' . $r['motor'] . ' n=' . $r['traduzidos']);
    json_saida(['api' => 'in3/v1', 'gerado_em' => agora(), 'cache' => 'sqlite', 'modelo' => traducao_modelo()] + $r);
}

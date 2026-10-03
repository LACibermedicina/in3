<?php
declare(strict_types=1);
/**
 * IN³ · folha.php — gera a folha de contato dos ícones (SVG e PNG) e a
 * arte de capa voxel, direto do acervo real do banco.
 *   php tools/folha.php [--png=arquivo.png] [--svg=arquivo.svg] [--capa=arquivo.png]
 * Sem fonte externa, sem rede, sem imagem de terceiros: tudo é desenhado.
 */
require_once dirname(__DIR__) . '/src/nucleo.php';

$op = [];
foreach ($argv as $a) {
    if (preg_match('/^--([a-z_]+)(?:=(.*))?$/i', $a, $m)) {
        $op[$m[1]] = $m[2] ?? true;
    }
}
$projetos = consulta('SELECT * FROM projetos ORDER BY mostrar_ao_publico DESC, categoria, titulo COLLATE NOCASE');

/* --------------------------------------------------------- cubo isométrico */
function cubo_iso(float $x, float $y, float $t, string $top, string $esq, string $dir): string
{
    $h = $t * 0.577;
    return '<g transform="translate(' . $x . ',' . $y . ')">'
        . '<polygon points="' . ($t / 2) . ',0 ' . $t . ',' . $h . ' ' . ($t / 2) . ',' . (2 * $h) . ' 0,' . $h . '" fill="' . $top . '"/>'
        . '<polygon points="0,' . $h . ' ' . ($t / 2) . ',' . (2 * $h) . ' ' . ($t / 2) . ',' . (2 * $h + $t) . ' 0,' . ($h + $t) . '" fill="' . $esq . '"/>'
        . '<polygon points="' . $t . ',' . $h . ' ' . ($t / 2) . ',' . (2 * $h) . ' ' . ($t / 2) . ',' . (2 * $h + $t) . ' ' . $t . ',' . ($h + $t) . '" fill="' . $dir . '"/>'
        . '</g>';
}

/* ------------------------------------------------------------- folha SVG */
$W = 1080;
$col = 4;
$cw = (int) ($W / $col);
$ch = 158;
$alt = 96 + (int) ceil(count($projetos) / $col) * $ch + 40;
$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $W . '" height="' . $alt . '" viewBox="0 0 ' . $W . ' ' . $alt . '">'
    . '<rect width="' . $W . '" height="' . $alt . '" fill="#F7FFFC"/>'
    . '<text x="32" y="46" font-family="Verdana,DejaVu Sans,sans-serif" font-size="26" font-weight="700" fill="#0B2026">IN&#179; · icones do acervo</text>'
    . '<text x="32" y="72" font-family="Verdana,DejaVu Sans,sans-serif" font-size="14" fill="#3E6B63">'
    . count($projetos) . ' projetos · cubo isometrico com glifo da categoria · selo PUBLICO / INTERNO</text>';
$i = 0;
foreach ($projetos as $p) {
    $x = 24 + ($i % $col) * $cw;
    $y = 96 + intdiv($i, $col) * $ch;
    $pub = (int) $p['mostrar_ao_publico'] === 1;
    $cor = (string) $p['cor'];
    $svg .= '<rect x="' . ($x - 8) . '" y="' . ($y - 8) . '" width="' . ($cw - 18) . '" height="' . ($ch - 18)
        . '" rx="16" fill="' . ($pub ? '#FFFFFF' : '#FFF8EA') . '" stroke="#CDEBE2"/>'
        . cubo_iso($x + 6.0, $y + 6.0, 62.0, escurecer($cor, -0.35), escurecer($cor, 0.16), $cor)
        . '<text x="' . $x . '" y="' . ($y + 108) . '" font-family="Verdana,DejaVu Sans,sans-serif" font-size="15" font-weight="700" fill="#122A2E">'
        . htmlspecialchars(mb_substr((string) $p['titulo'], 0, 20), ENT_XML1) . '</text>'
        . '<text x="' . $x . '" y="' . ($y + 126) . '" font-family="Verdana,DejaVu Sans,sans-serif" font-size="11" fill="#3BA794">'
        . htmlspecialchars(mb_substr((string) $p['categoria'], 0, 24), ENT_XML1) . '</text>'
        . '<text x="' . $x . '" y="' . ($y + 140) . '" font-family="Verdana,DejaVu Sans,sans-serif" font-size="11" font-weight="700" fill="'
        . ($pub ? '#0F7A46' : '#8A5A00') . '">' . ($pub ? 'PUBLICO · teto ' . strtoupper((string) $p['nivel_divulgacao']) : 'INTERNO · sob solicitacao') . '</text>';
    $i++;
}
$svg .= '</svg>';

/* ------------------------------------------------------------- folha PNG */
function png_folha(array $projetos, string $arq): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }
    $W = 1080;
    $col = 4;
    $cw = (int) ($W / $col);
    $ch = 158;
    $alt = 96 + (int) ceil(count($projetos) / $col) * $ch + 30;
    $im = imagecreatetruecolor($W, $alt);
    $branco = imagecolorallocate($im, 247, 255, 252);
    imagefilledrectangle($im, 0, 0, $W, $alt, $branco);
    $tinta = imagecolorallocate($im, 11, 32, 38);
    $fraco = imagecolorallocate($im, 62, 107, 99);
    imagestring($im, 5, 32, 24, 'IN3 - icones do acervo (' . count($projetos) . ' projetos)', $tinta);
    imagestring($im, 3, 32, 50, 'cubo isometrico + glifo da categoria + selo de publicacao', $fraco);

    $i = 0;
    foreach ($projetos as $p) {
        $x = 16 + ($i % $col) * $cw;
        $y = 78 + intdiv($i, $col) * $ch;
        $pub = (int) $p['mostrar_ao_publico'] === 1;
        [$r, $g, $b] = sscanf((string) $p['cor'], '#%02x%02x%02x') ?: [42, 149, 129];
        $fundo = imagecolorallocate($im, $pub ? 255 : 255, $pub ? 255 : 248, $pub ? 255 : 234);
        imagefilledrectangle($im, $x - 6, $y - 6, $x + $cw - 22, $y + $ch - 24, $fundo);
        imagerectangle($im, $x - 6, $y - 6, $x + $cw - 22, $y + $ch - 24, imagecolorallocate($im, 205, 235, 226));

        // cubo: topo claro, esquerda média, direita escura
        $t = 58;
        $h = (int) round($t * 0.577);
        $cx = $x + 4;
        $cy = $y + 4;
        $topo = imagecolorallocate($im, min(255, (int) $r + 95), min(255, (int) $g + 78), min(255, (int) $b + 72));
        $esq = imagecolorallocate($im, (int) ($r * 0.84), (int) ($g * 0.84), (int) ($b * 0.84));
        $dir = imagecolorallocate($im, $r, $g, $b);
        imagefilledpolygon($im, [$cx + $t / 2, $cy, $cx + $t, $cy + $h, $cx + $t / 2, $cy + 2 * $h, $cx, $cy + $h], 4, $topo);
        imagefilledpolygon($im, [$cx, $cy + $h, $cx + $t / 2, $cy + 2 * $h, $cx + $t / 2, $cy + 2 * $h + $t, $cx, $cy + $h + $t], 4, $esq);
        imagefilledpolygon($im, [$cx + $t, $cy + $h, $cx + $t / 2, $cy + 2 * $h, $cx + $t / 2, $cy + 2 * $h + $t, $cx + $t, $cy + $h + $t], 4, $dir);

        imagestring($im, 4, $x, $y + 104, mb_substr((string) $p['titulo'], 0, 19), $tinta);
        imagestring($im, 2, $x, $y + 122, mb_substr((string) $p['categoria'], 0, 26), imagecolorallocate($im, 59, 167, 148));
        imagestring($im, 2, $x, $y + 134, $pub ? 'PUBLICO' : 'INTERNO', $pub ? imagecolorallocate($im, 15, 122, 70)
            : imagecolorallocate($im, 138, 90, 0));
        $i++;
    }
    return imagepng($im, $arq);
}

/* ------------------------------------------------------------ capa voxel */
function png_capa(array $projetos, string $arq): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }
    $W = 1400; $H = 780;
    $im = imagecreatetruecolor($W, $H);
    // fundo em gradiente teal -> dourado
    for ($y = 0; $y < $H; $y++) {
        $f = $y / $H;
        $r = (int) (247 - 60 * $f); $g = (int) (255 - 30 * $f); $b = (int) (252 - 40 * $f);
        imagefilledrectangle($im, 0, $y, $W, $y, imagecolorallocate($im, max(0, $r), max(0, $g), max(0, $b)));
    }
    $pubs = array_values(array_filter($projetos, static fn(array $p): bool => (int) $p['mostrar_ao_publico'] === 1));
    $t = 74;
    $passoX = 86; $passoY = 50;
    $i = 0;
    foreach ($pubs as $p) {
        $cx = 150 + ($i % 6) * $passoX + intdiv($i, 6) * 30;
        $cy = 210 + intdiv($i, 6) * 96 + ($i % 6) * $passoY;
        [$r, $g, $b] = sscanf((string) $p['cor'], '#%02x%02x%02x') ?: [42, 149, 129];
        $h = (int) round($t * 0.577);
        $topo = imagecolorallocate($im, min(255, (int) $r + 95), min(255, (int) $g + 78), min(255, (int) $b + 72));
        $esq = imagecolorallocate($im, (int) ($r * 0.78), (int) ($g * 0.78), (int) ($b * 0.78));
        $dir = imagecolorallocate($im, $r, $g, $b);
        // laterais primeiro (ordem correta de profundidade visual)
        imagefilledpolygon($im, [$cx, $cy + $h, $cx + $t / 2, $cy + 2 * $h, $cx + $t / 2, $cy + 2 * $h + $t, $cx, $cy + $h + $t], 4, $esq);
        imagefilledpolygon($im, [$cx + $t, $cy + $h, $cx + $t / 2, $cy + 2 * $h, $cx + $t / 2, $cy + 2 * $h + $t, $cx + $t, $cy + $h + $t], 4, $dir);
        imagefilledpolygon($im, [$cx + $t / 2, $cy, $cx + $t, $cy + $h, $cx + $t / 2, $cy + 2 * $h, $cx, $cy + $h], 4, $topo);
        $i++;
    }
    // marca
    $tinta = imagecolorallocate($im, 11, 32, 38);
    imagefilledrectangle($im, 60, 48, 74, 62, $tinta);
    imagestring($im, 5, 86, 44, 'IN3  -  INCUBATOR', $tinta);
    imagestring($im, 3, 86, 68, 'portfolio vivo da incubadora m3d.pro', imagecolorallocate($im, 42, 149, 129));
    imagestring($im, 3, 86, 92, 'cena voxel isometrica - ' . count($pubs) . ' projetos publicados', imagecolorallocate($im, 58, 163, 186));
    return imagepng($im, $arq);
}

/* --------------------------------------------------------------- executar */
$svgArq = (string) ($op['svg'] ?? (IN3_RAIZ . '/folha-icones.svg'));
$pngArq = (string) ($op['png'] ?? (IN3_RAIZ . '/folha-icones.png'));
$capaArq = (string) ($op['capa'] ?? (IN3_RAIZ . '/capa-voxel.png'));

file_put_contents($svgArq, $svg);
fwrite(STDOUT, '  ✓ ' . $svgArq . ' (' . round(strlen($svg) / 1024) . " KB)\n");
fwrite(STDOUT, '  ' . (png_folha($projetos, $pngArq) ? '✓ ' . $pngArq : '! PNG indisponível (falta php-gd)') . "\n");
fwrite(STDOUT, '  ' . (png_capa($projetos, $capaArq) ? '✓ ' . $capaArq : '! capa PNG indisponível (falta php-gd)') . "\n");

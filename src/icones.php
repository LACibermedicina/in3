<?php
declare(strict_types=1);
/**
 * IN³ · ícones vetoriais + padrões voxel.
 * Tudo gerado localmente em SVG (sem CDN, sem fonte de ícone, sem requisição).
 */

/** Ícones de traço, 24×24, herdando currentColor. */
function icone_svg(string $nome, int $tam = 24, string $classe = 'ico'): string
{
    $t = [
        'cubo'        => '<path d="M12 2.5 21 7v10l-9 4.5L3 17V7z"/><path d="M3 7l9 4.5 9-4.5M12 21.5V11.5"/>',
        'telemedicina'=> '<path d="M8 3v3M16 3v3"/><rect x="3" y="6" width="18" height="13" rx="3"/><path d="M12 10v5M9.5 12.5h5"/>',
        'fiscal'      => '<path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4M9 12h6M9 16h6M9 8h2"/>',
        'catalogo'    => '<path d="M4 8h16l-1.2 12H5.2z"/><path d="M9 8a3 3 0 0 1 6 0"/>',
        'educacao'    => '<path d="M3 9l9-4 9 4-9 4z"/><path d="M7 12v4c0 1.7 2.2 3 5 3s5-1.3 5-3v-4"/>',
        'sensor'      => '<path d="M5 19a10 10 0 0 1 14 0"/><path d="M8 15a6 6 0 0 1 8 0"/><circle cx="12" cy="11" r="1.6"/>',
        'esporte'     => '<circle cx="12" cy="12" r="9"/><path d="M12 3v6M4 9l6 2M20 9l-6 2M7 20l5-5 5 5"/>',
        'vitrine'     => '<path d="M3 9V6h18v3"/><path d="M5 9v11h14V9"/><path d="M9 20v-6h6v6"/>',
        'ia'          => '<circle cx="12" cy="12" r="3"/><circle cx="12" cy="4" r="1.6"/><circle cx="12" cy="20" r="1.6"/><circle cx="4" cy="12" r="1.6"/><circle cx="20" cy="12" r="1.6"/><path d="M12 5.6v3M12 15.4v3M5.6 12h3M15.4 12h3"/>',
        'cirurgia'    => '<path d="M4 20l7-7M11 13l6-6a2.5 2.5 0 0 0-3.5-3.5l-6 6z"/><path d="M14 6l4 4"/>',
        'certificado' => '<circle cx="12" cy="9" r="5"/><path d="M9 13.5 8 22l4-2.5 4 2.5-1-8.5"/>',
        'governanca'  => '<path d="M3 21h18M5 21V10l7-5 7 5v11"/><path d="M9 21v-6h6v6"/>',
        'busca'       => '<circle cx="11" cy="11" r="7"/><path d="M16.5 16.5 21 21"/>',
        'escudo'      => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'chave'       => '<circle cx="8" cy="15" r="4"/><path d="M11 12 20 3M17 6l2 2M14 9l2 2"/>',
        'olho'        => '<path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="2.6"/>',
        'olho_fechado'=> '<path d="M3 3l18 18"/><path d="M10.6 6.2A9.4 9.4 0 0 1 12 6c6.4 0 10 6 10 6a17 17 0 0 1-2.8 3.4M6.5 8.2A17 17 0 0 0 2 12s3.6 6 10 6a9.7 9.7 0 0 0 3.4-.6"/>',
        'camadas'     => '<path d="M12 3 3 8l9 5 9-5z"/><path d="M3 13l9 5 9-5"/>',
        'grafo'       => '<circle cx="6" cy="7" r="2.4"/><circle cx="18" cy="6" r="2.4"/><circle cx="12" cy="18" r="2.4"/><path d="M8 8.5 11 16M16.2 7.6 13.4 15.6M8.4 6.7 15.6 6.2"/>',
        'codigo'      => '<path d="M9 18 3 12l6-6M15 6l6 6-6 6"/>',
        'relogio'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'pasta'       => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'usuario'     => '<circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/>',
        'sair'        => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 8 6 12l4 4M6 12h9"/>',
        'seta'        => '<path d="M5 12h13M13 6l6 6-6 6"/>',
        'predio'      => '<path d="M4 21V5l8-2v18M12 21h8V9l-8-2"/><path d="M7 8h2M7 12h2M7 16h2M15 12h2M15 16h2"/>',
    ][$nome] ?? $t['cubo'];

    return '<svg class="' . e($classe) . '" width="' . $tam . '" height="' . $tam . '" viewBox="0 0 24 24" '
        . 'fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true" focusable="false">' . $t . '</svg>';
}

/**
 * Semente determinística por projeto — o mesmo projeto gera sempre o mesmo
 * padrão voxel, sem guardar imagem nenhuma em disco.
 */
function semente(string $texto): int
{
    $h = 2166136261;
    foreach (str_split($texto) as $c) {
        $h ^= ord($c);
        $h = ($h * 16777619) & 0xFFFFFFFF;
    }
    return $h;
}

/**
 * Padrão voxel de um projeto: uma coluna de cubos cuja altura representa a
 * profundidade de informação do projeto (1..4) e cuja paleta vem da categoria.
 */
function voxel_projeto(array $p, bool $publico = true): array
{
    $s = semente((string) $p['slug']);
    $nivel = min(nivel_valor((string) $p['nivel_divulgacao']), $publico ? 4 : 4);
    $altura = 1 + $nivel;
    $paleta = paleta_categoria((string) $p['categoria']);
    $cubos = [];
    for ($y = 0; $y < $altura; $y++) {
        $cubos[] = [
            'x' => (($s >> ($y * 3)) & 3) - 1.5,
            'z' => (($s >> ($y * 5 + 1)) & 3) - 1.5,
            'y' => $y - ($altura - 1) / 2,
            'cor' => $paleta[$y % count($paleta)],
            'escala' => 0.62 + ((($s >> ($y * 2)) & 7) / 7) * 0.3,
        ];
    }
    return [
        'slug' => (string) $p['slug'],
        'titulo' => (string) $p['titulo'],
        'emoji' => (string) $p['emoji'],
        'icone' => (string) $p['icone'],
        'categoria' => (string) $p['categoria'],
        'nivel' => $nivel,
        'altura' => $altura,
        'paleta' => $paleta,
        'cubos' => $cubos,
    ];
}

/** Cores por categoria — derivadas da paleta oficial da marca IN³. */
function paleta_categoria(string $categoria): array
{
    $c = mb_strtolower($categoria);
    if (str_contains($c, 'telemedicina')) {
        return ['#47B29F', '#4CC9F0', '#7CC6B4'];
    }
    if (str_contains($c, 'fiscal')) {
        return ['#FFD166', '#FF9F45', '#47B29F'];
    }
    if (str_contains($c, 'comércio')) {
        return ['#9B8CFF', '#FF6B9D', '#4CC9F0'];
    }
    if (str_contains($c, 'educa')) {
        return ['#4CC9F0', '#7CC6B4', '#FFD166'];
    }
    if (str_contains($c, 'sensor') || str_contains($c, 'instrumenta')) {
        return ['#06D6A0', '#3BA794', '#FFD166'];
    }
    if (str_contains($c, 'pesquisa') || str_contains($c, 'ia')) {
        return ['#FF6B9D', '#9B8CFF', '#4CC9F0'];
    }
    if (str_contains($c, 'governan') || str_contains($c, 'infra')) {
        return ['#3BA794', '#167D6E', '#7CC6B4'];
    }
    return ['#47B29F', '#FFD166', '#4CC9F0'];
}

/** Cena voxel completa entregue ao Three.js (uma vez, no HTML). */
function cena_voxel(array $projetos): array
{
    $colunas = [];
    $x = 0;
    $largura = (int) ceil(sqrt(max(1, count($projetos))));
    foreach ($projetos as $p) {
        $v = voxel_projeto($p, (bool) $p['mostrar_ao_publico']);
        $v['pos'] = [
            'x' => ($x % $largura) - ($largura - 1) / 2,
            'z' => intdiv($x, $largura) - ($largura - 1) / 2,
        ];
        $colunas[] = $v;
        $x++;
    }
    return [
        'versao' => 1,
        'paleta' => [
            'fundo' => '#0F5C50',
            'teal' => '#47B29F',
            'profundo' => '#11695C',
            'amarelo' => '#FFD166',
            'azul' => '#4CC9F0',
            'tinta' => '#122A2E',
        ],
        'colunas' => $colunas,
    ];
}

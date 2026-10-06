<?php
declare(strict_types=1);
/**
 * IN³ · rotas públicas adicionais montadas fora do front controller:
 *   /feed            página do feed de notícias
 *   /idioma/{codigo} troca o idioma e volta para a página anterior
 *   /noticia/{slug}  notícia individual
 * Devolve true quando consumiu a requisição (o chamador encerra).
 */

function rota_extra(string $caminho): bool
{
    /* ---------------------------------------------------- trocar de idioma */
    if (preg_match('#^/idioma/([A-Za-z\-]{2,5})$#', $caminho, $m)) {
        $codigo = $m[1];
        if (preg_match('/^[a-z]{2}(\-[A-Za-z]{2})?$/', $codigo) !== 1) {
            nao_encontrado('Idioma inválido');
        }
        idioma_definir($codigo);
        $volta = (string) ($_GET['volta'] ?? '/');
        if (!str_starts_with($volta, '/') || str_starts_with($volta, '//')) {
            $volta = '/';
        }
        redirecionar($volta);
    }

    if (!banco_pronto() || !existe_admin()) {
        return false;
    }
    $clareza = clareza_visitante();

    /* ------------------------------------------------------------- /feed */
    if ($caminho === '/feed') {
        $filtro = ['categoria' => entrada('categoria')];
        $noticias = noticias_publicas($clareza, 24, $filtro);
        registrar_acesso('publico.feed', '', count($noticias));
        echo layout_publico(
            t('feed_titulo', 'Feed · IN³'),
            'Atualizações da incubadora IN³ traduzidas para linguagem humana, com detalhe técnico sob solicitação.',
            view('publico_feed.php', [
                'noticias' => $noticias,
                'clareza'  => $clareza,
                'filtro'   => $filtro,
                'stats'    => noticias_estatisticas(),
            ]),
            ['ativo' => 'feed', 'clareza' => $clareza,
             'json' => ['tipo' => 'feed', 'noticias' => array_map(static fn($n) => [
                 'slug' => $n['slug'], 'titulo' => $n['titulo'], 'resumo' => $n['resumo'] ?? '',
                 'tem_detalhe' => (bool) $n['tem_detalhe'],
             ], $noticias)]]
        );
        return true;
    }

    /* --------------------------------------------------- /noticia/{slug} */
    if (preg_match('#^/noticia/([a-z0-9\-]{2,120})$#', $caminho, $m)) {
        $n = noticia_por_slug($m[1]);
        if (!$n) {
            nao_encontrado('Notícia não publicada');
        }
        if ($clareza < 3) {
            unset($n['detalhe_tecnico']);
        }
        if ($clareza < 2) {
            unset($n['corpo']);
        }
        $proj = null;
        if (!empty($n['projeto_id'])) {
            $p = projeto_por_id((int) $n['projeto_id']);
            if ($p && (int) $p['mostrar_ao_publico'] === 1) {
                $proj = $p;
            }
        }
        registrar_acesso('publico.noticia', (string) $n['slug'], 1);
        echo layout_publico(
            $n['titulo'] . ' · IN³',
            texto_limpo((string) ($n['resumo'] ?? ''), 160),
            view('publico_noticia.php', ['n' => $n, 'projeto' => $proj, 'clareza' => $clareza]),
            ['ativo' => 'feed', 'clareza' => $clareza]
        );
        return true;
    }

    return false;
}

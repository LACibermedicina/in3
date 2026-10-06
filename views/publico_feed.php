<section class="feed-pagina">
  <header class="feed-hero">
    <h1><?= e(t('feed_titulo', 'O que mudou por aqui')) ?></h1>
    <p class="miudo"><?= (int) $stats['publicadas'] ?> publicações visíveis ·
      <?= e(t('feed_dica', '')) ?></p>
  </header>
  <ol class="feed-lista feed-lista-grande">
    <?php foreach ($noticias as $n): ?>
      <li class="feed-item">
        <time class="feed-data" datetime="<?= e(substr((string) $n['data'], 0, 10)) ?>"><?= e(data_br((string) $n['data'])) ?></time>
        <div class="feed-corpo">
          <h2 class="feed-titulo"><a href="<?= e(url('/noticia/' . $n['slug'])) ?>"><?= e((string) $n['titulo']) ?></a></h2>
          <p><?= e(texto_limpo((string) ($n['resumo'] ?? ''), 400)) ?></p>
          <?php if (!empty($n['corpo'])): ?><div class="feed-texto"><?= nl2br(e(texto_limpo((string) $n['corpo'], 1200))) ?></div><?php endif; ?>
          <p class="feed-selos miudo">
            <?php if (!empty($n['projeto_slug'])): ?>
              <a class="selo" href="<?= e(url('/p/' . $n['projeto_slug'])) ?>"><?= e((string) ($n['projeto_emoji'] ?? '')) ?> <?= e((string) $n['projeto_titulo']) ?></a>
            <?php endif; ?>
            <?php if (!empty($n['categoria'])): ?><span class="selo"><?= e((string) $n['categoria']) ?></span><?php endif; ?>
          </p>
          <?php if (!empty($n['tem_detalhe'])): ?>
            <details class="tecnico">
              <summary><?= e(t('revelar_tecnico', 'Revelar detalhes técnicos')) ?></summary>
              <pre class="tecnico-txt"><?= e((string) ($n['detalhe_tecnico'] ?? '')) ?></pre>
            </details>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
    <?php if (!$noticias): ?><li class="feed-vazio miudo"><?= e(t('feed_vazio', 'Nenhuma publicação liberada ainda.')) ?></li><?php endif; ?>
  </ol>
</section>

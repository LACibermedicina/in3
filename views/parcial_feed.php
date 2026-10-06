<?php
/** IN³ · feed de notícias — resumo público, detalhe técnico sob solicitação. */
$ultimas = noticias_publicas($clareza, 6);
if (!$ultimas) {
    return;
}
?>
<section class="feed">
  <header class="feed-cab">
    <div>
      <h2><?= e(t('feed_titulo', 'O que mudou por aqui')) ?></h2>
      <p class="miudo"><?= e(t('feed_dica', 'Atividade técnica traduzida para linguagem humana. O detalhe técnico aparece só se você pedir — e só no nível liberado.')) ?></p>
    </div>
    <a class="botao botao-mini" href="<?= e(url('/feed')) ?>"><?= e(t('ver_tudo', 'Ver tudo')) ?></a>
  </header>
  <ol class="feed-lista">
    <?php foreach ($ultimas as $n): ?>
      <li class="feed-item">
        <time class="feed-data" datetime="<?= e(substr((string) $n['data'], 0, 10)) ?>"><?= e(data_br((string) $n['data'])) ?></time>
        <div class="feed-corpo">
          <h3><a href="<?= e(url('/noticia/' . $n['slug'])) ?>"><?= e((string) $n['titulo']) ?></a></h3>
          <p><?= e(texto_limpo((string) ($n['resumo'] ?? ''), 260)) ?></p>
          <p class="feed-selos miudo">
            <?php if (!empty($n['projeto_slug'])): ?>
              <a class="selo" href="<?= e(url('/p/' . $n['projeto_slug'])) ?>"><?= e((string) ($n['projeto_emoji'] ?? '')) ?> <?= e((string) $n['projeto_titulo']) ?></a>
            <?php endif; ?>
            <?php if (!empty($n['categoria'])): ?><span class="selo"><?= e((string) $n['categoria']) ?></span><?php endif; ?>
            <span class="selo selo-fonte"><?= e((string) $n['fonte']) ?></span>
          </p>
          <?php if (!empty($n['tem_detalhe'])): ?>
            <details class="tecnico">
              <summary><?= e(t('revelar_tecnico', 'Revelar detalhes técnicos')) ?></summary>
              <pre class="tecnico-txt"><?= e((string) ($n['detalhe_tecnico'] ?? '')) ?></pre>
            </details>
          <?php else: ?>
            <p class="miudo feed-pedir">
              <?= e(t('sem_tecnico', 'O detalhe técnico deste item está em camada superior.')) ?>
              <a href="<?= e(url('/pedido')) ?>"><?= e(t('pedir_detalhamento', 'Pedir detalhamento')) ?></a>
            </p>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
</section>

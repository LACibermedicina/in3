<article class="noticia">
  <p class="miudo"><a href="<?= e(url('/feed')) ?>">← <?= e(t('voltar_feed', 'Voltar ao feed')) ?></a></p>
  <h1><?= e((string) $n['titulo']) ?></h1>
  <p class="miudo"><?= e(data_br((string) $n['data'])) ?>
    <?php if (!empty($n['autor'])): ?> · <?= e((string) $n['autor']) ?><?php endif; ?>
    <?php if (!empty($n['repo'])): ?> · <code><?= e((string) $n['repo']) ?></code><?php endif; ?>
  </p>
  <p class="noticia-resumo"><?= e((string) $n['resumo']) ?></p>
  <?php if (!empty($n['corpo'])): ?><div class="feed-texto"><?= nl2br(e((string) $n['corpo'])) ?></div><?php endif; ?>
  <?php if (!empty($n['detalhe_tecnico'])): ?>
    <details class="tecnico" open>
      <summary><?= e(t('detalhe_tecnico', 'Detalhe técnico')) ?></summary>
      <pre class="tecnico-txt"><?= e((string) $n['detalhe_tecnico']) ?></pre>
    </details>
  <?php else: ?>
    <p class="miudo"><?= e(t('sem_tecnico', 'O detalhe técnico deste item está em camada superior.')) ?>
      <a href="<?= e(url('/pedido')) ?>"><?= e(t('pedir_detalhamento', 'Pedir detalhamento')) ?></a></p>
  <?php endif; ?>
  <?php if ($projeto): ?>
    <p><a class="botao" href="<?= e(url('/p/' . $projeto['slug'])) ?>"><?= e((string) $projeto['emoji']) ?> <?= e((string) $projeto['titulo']) ?></a></p>
  <?php endif; ?>
</article>

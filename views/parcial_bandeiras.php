<?php
/** IN³ · seletor de idiomas por bandeiras (5 idiomas exatos). */
$atual  = idioma_atual();
$volta  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$ia     = traducao_configurada();
?>
<div class="idiomas" data-idiomas role="group" aria-label="<?= e(t('idioma_rotulo', 'Idioma da página')) ?>">
  <button type="button" class="idiomas-atual" data-idiomas-abrir
          aria-haspopup="true" aria-expanded="false"
          title="<?= e($ia ? 'Tradução por IA ativa' : 'Tradução por dicionário (sem chave de IA)') ?>">
    <span class="idiomas-bandeira" aria-hidden="true"><?= e(IN3_IDIOMAS[$atual]['bandeira']) ?></span>
    <span class="idiomas-cod"><?= e(strtoupper(str_replace('-', '-', $atual))) ?></span>
    <span class="idiomas-seta" aria-hidden="true">▾</span>
  </button>
  <ul class="idiomas-lista" data-idiomas-lista role="menu">
    <?php foreach (IN3_IDIOMAS as $cod => $info): ?>
      <li role="none">
        <a role="menuitem" class="idiomas-op <?= $cod === $atual ? 'on' : '' ?>"
           data-idioma="<?= e($cod) ?>"
           href="<?= e(url('/idioma/' . rawurlencode($cod) . '?volta=' . rawurlencode($volta))) ?>"
           hreflang="<?= e($cod) ?>"
           title="<?= e($info['rotulo'] . ' · ' . $info['nativo']) ?>">
          <span class="idiomas-bandeira" aria-hidden="true"><?= e($info['bandeira']) ?></span>
          <span class="idiomas-nome"><?= e($info['nativo']) ?></span>
          <span class="idiomas-rot"><?= e($info['rotulo']) ?></span>
          <?php if ($cod === $atual): ?><span class="idiomas-ok" aria-hidden="true">✓</span><?php endif; ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
  <span class="idiomas-selo <?= $ia ? 'ia' : 'dic' ?>"
        title="<?= e($ia ? 'Motor de IA configurado no servidor' : 'Sem chave de IA: dicionário estático') ?>">
    <?= $ia ? 'IA' : 'DIC' ?>
  </span>
</div>

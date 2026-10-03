<section class="pagina">
  <h1><?= icone_svg('grafo', 26) ?> Tecnologias</h1>
  <p class="miudo">Contagem por projeto visível. Só entram tecnologias declaradas em projetos liberados nesta profundidade.</p>
  <div class="nuvem-chips">
    <?php foreach ($tecnologias as $t): ?>
      <a class="chip chip-tec" href="<?= e(url('/busca?q=' . urlencode((string) $t['tech']))) ?>">
        <?= e((string) $t['tech']) ?> <span class="chip-n"><?= (int) $t['n'] ?></span></a>
    <?php endforeach; ?>
    <?php if (!$tecnologias): ?><p class="miudo">Nenhuma tecnologia declarada ainda.</p><?php endif; ?>
  </div>
</section>

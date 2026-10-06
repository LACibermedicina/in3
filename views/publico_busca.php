<section class="pagina">
  <h1><?= icone_svg('busca', 26) ?> Busca no portfólio</h1>
  <p class="miudo">A consulta roda no servidor já filtrada: o seu nível de acesso e o teto de cada projeto decidem, em SQL,
     o que pode voltar. Não existe resultado "escondido na página".</p>

  <form class="form-busca" method="get" action="<?= e(url('/busca')) ?>" role="search">
    <label class="sr" for="q">Termo de busca</label>
    <input id="q" name="q" type="search" minlength="2" required value="<?= e($termo) ?>"
           placeholder="Ex.: telemedicina, LGPD, telemetria, Wi-Fi, IRPF…" autocomplete="off">
    <label class="sr" for="categoria">Área</label>
    <select id="categoria" name="categoria">
      <option value="">Todas as áreas</option>
      <?php foreach ($categorias as $c): ?>
        <option value="<?= e((string) $c['categoria']) ?>" <?= ($filtro['categoria'] ?? '') === (string) $c['categoria'] ? 'selected' : '' ?>>
          <?= e((string) $c['categoria']) ?> (<?= (int) $c['n'] ?>)</option>
      <?php endforeach; ?>
    </select>
    <button class="botao" type="submit"><?= icone_svg('busca', 16) ?> Buscar</button>
  </form>

  <?php if ($resultado !== null): ?>
    <p class="resultado-resumo">
      <strong><?= (int) $resultado['total'] ?></strong> resultado(s) para
      <mark><?= e($termo) ?></mark>
      <span class="miudo">· motor: <?= e((string) $resultado['motor']) ?>
        · profundidade máxima consultada: <?= e(nivel_rotulo((int) min(4, $clareza))) ?>
        <?= !empty($resultado['aviso']) ? ' · ' . e((string) $resultado['aviso']) : '' ?></span>
    </p>

    <?php if (!$resultado['resultados']): ?>
      <div class="vazio">
        <p>Nada encontrado nesta profundidade.</p>
        <p class="miudo">Pode ser que o conteúdo exista em uma camada superior (técnica ou playbook).
           Nesse caso ele não é servido publicamente — <a href="<?= e(url('/pedido')) ?>">peça o detalhamento</a>.</p>
      </div>
    <?php endif; ?>

    <ol class="resultados">
      <?php foreach ($resultado['resultados'] as $r): ?>
        <li>
          <a class="resultado-titulo" href="<?= e(url('/p/' . $r['slug'])) ?>"><?= e((string) $r['titulo']) ?></a>
          <span class="chip"><?= e((string) $r['categoria']) ?></span>
          <span class="etq etq-<?= (int) $r['profundidade'] >= 3 ? 'aberto' : 'parcial' ?>">
            <?= icone_svg('camadas', 13) ?> achado na camada <?= (int) $r['profundidade'] ?> — <?= e(nivel_rotulo((int) $r['profundidade'])) ?></span>
          <p class="resultado-trecho"><?= realcar((string) $r['trecho'], $termo) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php else: ?>
    <div class="vazio">
      <p>Digite um termo para começar.</p>
      <p class="miudo">Sugestões: <?php foreach (array_slice($categorias, 0, 6) as $c): ?>
        <a class="chip" href="<?= e(url('/busca?q=' . urlencode((string) $c['categoria']))) ?>"><?= e((string) $c['categoria']) ?></a>
      <?php endforeach; ?></p>
    </div>
  <?php endif; ?>
</section>

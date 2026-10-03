<section class="pagina">
  <h1><?= icone_svg('busca', 26) ?> Busca interna</h1>
  <p class="miudo">Aqui você consulta com a sua clareza (até a camada <?= (int) max(1, min(4, (int) ($clareza ?? 4))) ?>).
     A mesma consulta feita por um visitante anônimo só alcançaria a camada 1 — o filtro é aplicado no SQL.</p>

  <form class="form-busca" method="get" action="<?= e(url_painel('/busca')) ?>" role="search">
    <label class="sr" for="q">Termo</label>
    <input id="q" name="q" type="search" required minlength="2" value="<?= e($termo) ?>" placeholder="Ex.: LGPD, telemetria, arquitetura">
    <label class="sr" for="categoria">Área</label>
    <select id="categoria" name="categoria">
      <option value="">Todas as áreas</option>
      <?php foreach ($categorias as $c): ?>
        <option value="<?= e((string) $c['categoria']) ?>" <?= (entrada('categoria') === (string) $c['categoria']) ? 'selected' : '' ?>>
          <?= e((string) $c['categoria']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="botao" type="submit"><?= icone_svg('busca', 16) ?> Buscar</button>
  </form>

  <?php if ($resultado !== null): ?>
    <p class="resultado-resumo"><strong><?= (int) $resultado['total'] ?></strong> resultado(s) para <mark><?= e($termo) ?></mark>
      <span class="miudo">· motor: <?= e((string) $resultado['motor']) ?></span></p>
    <ol class="resultados">
      <?php foreach ($resultado['resultados'] as $r): ?>
        <li>
          <a class="resultado-titulo" href="<?= e(url('/p/' . $r['slug'])) ?>"><?= e((string) $r['titulo']) ?></a>
          <span class="chip"><?= e((string) $r['categoria']) ?></span>
          <span class="etq etq-parcial">camada <?= (int) $r['profundidade'] ?> · <?= e(nivel_rotulo((int) $r['profundidade'])) ?></span>
          <p class="resultado-trecho"><?= realcar((string) $r['trecho'], $termo) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>
</section>

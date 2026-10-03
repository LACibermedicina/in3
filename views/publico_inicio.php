<section class="ini">
  <div class="ini-hero">
    <h1>IN³ · portfólio vivo da incubadora</h1>
    <p class="ini-slogan"><?= e((string) ($manifesto !== '' ? $manifesto : 'O que entra na m3d, sai ao cubo.')) ?></p>
    <p class="miudo"><?= (int) $stats['projetos'] ?> projetos publicados ·
       <?= (int) $stats['repos'] ?> repositórios inventariados ·
       <?= (int) $stats['categorias'] ?> áreas<?= $stats['atualizado'] !== '' ? ' · atualizado em ' . e(data_br((string) $stats['atualizado'])) : '' ?></p>
  </div>

  <div class="cena" id="cena-voxel" role="img"
       aria-label="Cena interativa tridimensional com um cubo iluminado por projeto do portfólio. Pode ser girada com o mouse, com as setas do teclado ou arrastando o dedo.">
    <canvas id="voxel-canvas" aria-hidden="true"></canvas>
    <div class="cena-controles">
      <button type="button" class="botao botao-mini" data-cena="girar"><?= icone_svg('grafo', 15) ?> Girar</button>
      <button type="button" class="botao botao-mini" data-cena="explodir"><?= icone_svg('camadas', 15) ?> Explodir</button>
      <button type="button" class="botao botao-mini" data-cena="reset"><?= icone_svg('relogio', 15) ?> Repor</button>
      <span class="cena-dica miudo">1 cubo = 1 projeto · altura = profundidade de informação liberada</span>
    </div>
    <p class="cena-alt" id="cena-alt">Alternativa textual: a lista de projetos abaixo descreve tudo o que a cena mostra.</p>
  </div>

  <div class="filtros" role="group" aria-label="Filtros do portfólio">
    <button type="button" class="chip chip-f on" data-filtro="todas">Todas as áreas</button>
    <?php foreach ($categorias as $c): ?>
      <button type="button" class="chip chip-f" data-filtro="<?= e((string) $c['categoria']) ?>">
        <?= e((string) $c['categoria']) ?> <span class="chip-n"><?= (int) $c['n'] ?></span>
      </button>
    <?php endforeach; ?>
  </div>

  <div class="grade" id="grade-projetos">
    <?php foreach ($projetos as $p): ?>
      <?= cartao_projeto($p, $clareza) ?>
    <?php endforeach; ?>
  </div>

  <section class="faixa-niveis">
    <h2><?= icone_svg('camadas', 20) ?> Como a informação é liberada</h2>
    <p class="miudo">Cada projeto tem um teto de profundidade definido na curadoria. O que você vê é o menor valor entre
       esse teto e o seu nível de acesso — e a busca respeita exatamente a mesma regra, no servidor.</p>
    <ol class="niveis-lista">
      <?php foreach (IN3_NIVEL_ROTULO as $n => $rot): ?>
        <li class="nivel-item <?= $clareza >= $n ? 'on' : '' ?>">
          <span class="nivel-num"><?= (int) $n ?></span>
          <strong><?= e($rot) ?></strong>
          <span class="miudo"><?= $clareza >= $n ? 'liberado para a sua visita' : 'disponível sob pedido' ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
    <a class="botao" href="<?= e(url('/pedido')) ?>"><?= icone_svg('chave', 16) ?> Pedir detalhamento</a>
  </section>
</section>

<article class="hotsite" data-slug="<?= e((string) $p['slug']) ?>">
  <nav class="migalhas miudo" aria-label="Trilha de navegação">
    <a href="<?= e(url('/')) ?>">Portfólio</a> <span aria-hidden="true">›</span>
    <a href="<?= e(url('/busca?categoria=' . urlencode((string) $p['categoria']))) ?>"><?= e((string) $p['categoria']) ?></a>
    <span aria-hidden="true">›</span> <span><?= e((string) $p['titulo']) ?></span>
  </nav>

  <header class="hotsite-topo" style="--c1:<?= e(paleta_categoria((string) $p['categoria'])[0]) ?>;--c2:<?= e(paleta_categoria((string) $p['categoria'])[1]) ?>">
    <span class="hotsite-ico"><?= icone_svg((string) $p['icone'], 40) ?></span>
    <div>
      <h1><span aria-hidden="true"><?= e((string) $p['emoji']) ?></span> <?= e((string) $p['titulo']) ?></h1>
      <p class="miudo"><?= e((string) $p['categoria']) ?> · <?= e((string) $p['status']) ?>
        · <?= icone_svg('camadas', 13) ?> teto do projeto: <strong><?= e(nivel_rotulo($montagem['nivel_projeto'])) ?></strong></p>
      <?= barra_pilha($montagem['secoes']) ?>
      <p class="miudo">Sua profundidade de leitura agora:
        <strong><?= e(nivel_rotulo((int) $montagem['profundidade'])) ?></strong></p>
    </div>
  </header>

  <?php foreach ($montagem['secoes'] as $sec): ?>
    <?php if (!empty($sec['obrigatoria']) || !empty($sec['linhas']) || !empty($sec['texto'])): ?>
      <section class="bloco" id="<?= e((string) $sec['chave']) ?>">
        <h2><?= e((string) $sec['titulo']) ?></h2>
        <?php if (!empty($sec['texto'])): ?>
          <p class="destaque"><?= e((string) $sec['texto']) ?></p>
        <?php endif; ?>
        <?php if (!empty($sec['campos'])): ?>
          <dl class="ficha">
            <?php foreach ($sec['campos'] as $rot => $val): ?>
              <?php if (campo_publicavel((string) $val)): ?>
                <div><dt><?= e((string) $rot) ?></dt><dd><?= e((string) $val) ?></dd></div>
              <?php endif; ?>
            <?php endforeach; ?>
          </dl>
        <?php endif; ?>
        <?php if (!empty($sec['linhas'])): ?>
          <ol class="linha-tempo">
            <?php foreach ($sec['linhas'] as $l): ?>
              <li><time><?= e((string) $l['data']) ?></time><span><?= e((string) $l['marco']) ?></span></li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
        <?php if (!empty($sec['tecnologias'])): ?>
          <div class="cartao-chips"><?php foreach ($sec['tecnologias'] as $t): ?><span class="chip"><?= e((string) $t) ?></span><?php endforeach; ?></div>
        <?php endif; ?>
        <?php if (!empty($sec['repositorios'])): ?>
          <ul class="lista-repos">
            <?php foreach ($sec['repositorios'] as $r): ?>
              <li><a href="<?= e((string) $r['url']) ?>" rel="noopener nofollow"><?= icone_svg('codigo', 15) ?> <?= e((string) $r['nome']) ?></a>
                <span class="chip"><?= e((string) ($r['linguagem'] !== '' ? $r['linguagem'] : 'sem linguagem')) ?></span>
                <span class="miudo"><?= e(data_br((string) $r['gh_atualizado_em'])) ?></span></li>
            <?php endforeach; ?>
          </ul>
        <?php elseif (isset($sec['repos_contagem']) && $sec['repos_contagem'] > 0 && empty($sec['exibe_repos'])): ?>
          <p class="miudo"><?= icone_svg('olho_fechado', 14) ?> <?= (int) $sec['repos_contagem'] ?> repositório(s) deste
             projeto <strong>não estão expostos publicamente</strong> (decisão de curadoria).</p>
        <?php endif; ?>
        <?php if (!empty($sec['interno'])): ?>
          <p class="selo-interno miudo"><?= icone_svg('escudo', 14) ?> conteúdo interno — visível apenas no painel autenticado.</p>
        <?php endif; ?>
      </section>
    <?php elseif ($sec['profundidade'] > 1): ?>
      <section class="bloco bloco-trancado" id="<?= e((string) $sec['chave']) ?>">
        <h2><?= icone_svg('olho_fechado', 18) ?> <?= e((string) $sec['titulo']) ?></h2>
        <p class="miudo">Camada <?= (int) $sec['profundidade'] ?> ·
           <?= e(nivel_rotulo((int) $sec['profundidade'])) ?>.
           Este bloco existe, mas o conteúdo não é servido nesta profundidade.</p>
        <p class="miudo">A página não envia o texto bloqueado nem escondido no HTML: ele simplesmente não sai do servidor.</p>
        <?php if ($clareza < 4): ?>
          <form class="form-mini" method="post" action="<?= e(url('/pedido')) ?>">
            <?= $csrf ?>
            <input type="hidden" name="projeto" value="<?= e((string) $p['titulo']) ?>">
            <input type="hidden" name="nivel" value="<?= (int) $sec['profundidade'] >= 4 ? 'playbook' : ((int) $sec['profundidade'] === 3 ? 'tecnico' : 'institucional') ?>">
            <input type="hidden" name="nome" value="">
            <p class="miudo">Para receber esta camada, use o formulário de pedido informando seus dados de contato.</p>
            <a class="botao botao-mini" href="<?= e(url('/pedido?projeto=' . urlencode((string) $p['titulo']))) ?>">
              <?= icone_svg('chave', 14) ?> Solicitar nível <?= e(nivel_rotulo((int) $sec['profundidade'])) ?></a>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  <?php endforeach; ?>

  <section class="bloco">
    <h2><?= icone_svg('cubo', 18) ?> Verificação de liberação</h2>
    <p class="miudo">Esta página passou pela checagem automática <code>tools/verificar.php</code>: nenhum trecho de camada
       bloqueada é emitido no HTML, e a mesma política vale para a API (<code>/api/v1/publico/portfolio</code>).</p>
    <ul class="lista-check miudo">
      <?php foreach ($montagem['secoes'] as $sec): ?>
        <li><?= $sec['liberada'] ? icone_svg('escudo', 14) : icone_svg('olho_fechado', 14) ?>
           <?= e((string) $sec['titulo']) ?> — camada <?= (int) $sec['profundidade'] ?>
           <?= $sec['liberada'] ? '<strong>liberada</strong>' : 'não servida' ?></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <?php if ($relacionados): ?>
    <section class="bloco">
      <h2>Outros projetos desta área</h2>
      <div class="grade"><?php foreach ($relacionados as $r): ?><?= cartao_projeto($r, $clareza) ?><?php endforeach; ?></div>
    </section>
  <?php endif; ?>
</article>

<section class="pagina passo-<?= (int) $passo ?>">
  <h1><?= icone_svg('escudo', 24) ?> Instalação · etapa <?= (int) $passo ?> de 3</h1>
  <ol class="passos">
    <li class="<?= $passo === 1 ? 'on' : ($passo > 1 ? 'feito' : '') ?>">Ambiente &amp; banco</li>
    <li class="<?= $passo === 2 ? 'on' : ($passo > 2 ? 'feito' : '') ?>">Administrador</li>
    <li class="<?= $passo === 3 ? 'on' : '' ?>">Concluído</li>
  </ol>

  <?php if ($erros): ?>
    <div class="aviso aviso-erro"><ul><?php foreach ($erros as $er) : ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <?php if ($passo === 1): ?>
    <h2>Ambiente detectado</h2>
    <ul class="lista-check">
      <?php foreach ($estado['checks'] as [$rot, $ok, $det]): ?>
        <li><?= $ok ? icone_svg('escudo', 15) : icone_svg('olho_fechado', 15) ?>
          <strong><?= e((string) $rot) ?></strong> — <span class="miudo"><?= e((string) $det) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <p class="miudo">A etapa 1 cria o banco SQLite em <code>data/in3.db</code>, aplica o esquema, liga a busca
       (FTS5 quando disponível) e define as configurações iniciais. Nada de dado de exemplo é publicado.</p>
    <form method="post" action="<?= e($rota) ?>">
      <?= $csrf ?><input type="hidden" name="acao" value="esquema">
      <button class="botao botao-forte" type="submit" <?= $estado['ok'] ? '' : 'disabled' ?>>
        <?= icone_svg('pasta', 16) ?> Criar banco e aplicar esquema</button>
    </form>

  <?php elseif ($passo === 2): ?>
    <h2>Primeiro administrador</h2>
    <p class="miudo">Este usuário terá clareza máxima (camada 4) e poderá criar os demais. A senha é gravada apenas
       como hash — não há senha padrão em lugar nenhum do projeto.</p>
    <form method="post" action="<?= e($rota) ?>" class="form-bloco">
      <?= $csrf ?><input type="hidden" name="acao" value="admin">
      <div class="campo"><label for="usuario">Usuário *</label>
        <input id="usuario" name="usuario" required pattern="[a-zA-Z0-9._-]{3,40}" maxlength="40"></div>
      <div class="campo"><label for="nome">Nome</label><input id="nome" name="nome" maxlength="120"></div>
      <div class="campo"><label for="email">E-mail</label><input id="email" name="email" type="email" maxlength="160"></div>
      <div class="campo"><label for="senha">Senha * (mínimo 10 caracteres)</label>
        <input id="senha" name="senha" type="password" required minlength="10" autocomplete="new-password"></div>
      <div class="campo"><label for="senha2">Repita a senha *</label>
        <input id="senha2" name="senha2" type="password" required minlength="10" autocomplete="new-password"></div>
      <div class="campo"><label for="conta_github">Conta GitHub do acervo</label>
        <input id="conta_github" name="conta_github" value="<?= e($conta_github) ?>" maxlength="60"></div>
      <button class="botao botao-forte" type="submit"><?= icone_svg('usuario', 16) ?> Criar administrador</button>
    </form>

  <?php else: ?>
    <h2>Tudo pronto</h2>
    <p>O banco foi criado e o administrador existe. O site público já está no ar.</p>
    <ul class="lista-check">
      <li><?= icone_svg('escudo', 15) ?> Site público: <a href="<?= e(url('/')) ?>"><?= e(url('/')) ?></a></li>
      <li><?= icone_svg('chave', 15) ?> Painel (rota não listada no site): <strong><?= e((string) $rota_painel) ?>/entrar</strong></li>
    </ul>
    <p class="miudo">A rota do painel não aparece em nenhum link, no <code>robots.txt</code> nem no sitemap do site público.
       Para alterá-la, edite <code>IN3_ROTA_PAINEL</code> no <code>.env</code>.</p>
    <p>
      <a class="botao botao-forte" href="<?= e((string) $rota_painel) ?>/entrar">Abrir o painel</a>
      <a class="botao" href="<?= e(url('/')) ?>">Ver o site</a>
    </p>
    <p class="miudo">Próximo passo recomendado: no painel, use <em>Sistema › Sincronizar GitHub</em> informando um token de
       leitura para inventariar também os repositórios privados. O token é usado só naquela chamada e nunca é gravado.</p>
  <?php endif; ?>
</section>

<section class="entrar">
  <div class="entrar-caixa">
    <h1><?= icone_svg('escudo', 24) ?> Painel IN³</h1>
    <p class="miudo">Área restrita. As credenciais são validadas contra hash (Argon2/bcrypt) e limitadas por tentativas.</p>
    <form method="post" action="<?= e($rota) ?>">
      <?= $csrf ?>
      <div class="campo"><label for="usuario">Usuário</label>
        <input id="usuario" name="usuario" required autocomplete="username" autofocus maxlength="40"></div>
      <div class="campo"><label for="senha">Senha</label>
        <input id="senha" name="senha" type="password" required autocomplete="current-password" minlength="10"></div>
      <button class="botao botao-forte" type="submit"><?= icone_svg('chave', 16) ?> Entrar</button>
    </form>
    <p class="miudo">Esqueceu a senha? Redefina pela linha de comando com
      <code>php tools/senha.php --usuario=SEU_USUARIO</code> — a senha nunca é exibida nem gravada em log.</p>
  </div>
</section>

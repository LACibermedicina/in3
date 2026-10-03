<section class="pagina">
  <h1><?= icone_svg('usuario', 26) ?> Usuários</h1>
  <p class="miudo"><?= icone_svg('escudo', 15) ?> Nenhuma senha é exibida, exportada ou registrada em log — apenas hash.
     Ao redefinir, todas as sessões do usuário são encerradas.</p>

  <table class="tabela">
    <thead><tr><th>Usuário</th><th>Nome</th><th>Papel</th><th>Clareza</th><th>Ativo</th><th>Último acesso</th><th>Ações</th></tr></thead>
    <tbody>
    <?php foreach ($usuarios as $u): ?>
      <tr>
        <td><code><?= e((string) $u['usuario']) ?></code><br><span class="miudo"><?= e((string) $u['email']) ?: '—' ?></span></td>
        <td><?= e((string) $u['nome']) ?: '—' ?></td>
        <td><span class="chip"><?= e((string) $u['papel']) ?></span></td>
        <td><?= e(nivel_rotulo((int) $u['clareza'])) ?></td>
        <td><?= (int) $u['ativo'] === 1 ? '<span class="etq etq-aberto">ativo</span>' : '<span class="etq etq-interno">inativo</span>' ?></td>
        <td class="miudo"><?= $u['ultimo_acesso'] ? e(data_br((string) $u['ultimo_acesso'], true)) : '—' ?></td>
        <td>
          <form method="post" action="<?= e(url_painel('/usuarios')) ?>" class="form-inline">
            <?= $csrf ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <select name="papel" aria-label="Papel">
              <?php foreach (['admin','curador','leitor'] as $pp): ?>
                <option value="<?= e($pp) ?>" <?= $u['papel'] === $pp ? 'selected' : '' ?>><?= e($pp) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="botao botao-mini" name="acao" value="papel" type="submit">Papel</button>
            <select name="clareza" aria-label="Clareza">
              <?php foreach ($niveis as $n => $rot): ?>
                <option value="<?= (int) $n ?>" <?= (int) $u['clareza'] === (int) $n ? 'selected' : '' ?>><?= (int) $n ?> · <?= e($rot) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="botao botao-mini" name="acao" value="clareza" type="submit">Clareza</button>
            <input name="senha" type="password" minlength="10" placeholder="nova senha" aria-label="Nova senha" class="in-senha">
            <button class="botao botao-mini" name="acao" value="senha" type="submit">Redefinir</button>
            <?php if ((int) $u['ativo'] === 1): ?>
              <button class="botao botao-mini botao-fantasma" name="acao" value="desativar" type="submit">Desativar</button>
            <?php else: ?>
              <button class="botao botao-mini" name="acao" value="ativar" type="submit">Ativar</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <section class="bloco">
    <h2><?= icone_svg('usuario', 18) ?> Criar usuário</h2>
    <form method="post" action="<?= e(url_painel('/usuarios')) ?>" class="form-bloco">
      <?= $csrf ?><input type="hidden" name="acao" value="criar">
      <div class="linha-campos">
        <div class="campo"><label for="usuario">Usuário *</label>
          <input id="usuario" name="usuario" required pattern="[a-zA-Z0-9._-]{3,40}"></div>
        <div class="campo"><label for="nome">Nome</label><input id="nome" name="nome" maxlength="120"></div>
        <div class="campo"><label for="email">E-mail</label><input id="email" name="email" type="email" maxlength="160"></div>
      </div>
      <div class="linha-campos">
        <div class="campo"><label for="senha">Senha * (mínimo 10)</label>
          <input id="senha" name="senha" type="password" required minlength="10" autocomplete="new-password"></div>
        <div class="campo"><label for="papel">Papel</label>
          <select id="papel" name="papel"><option value="leitor">leitor</option><option value="curador">curador</option>
            <option value="admin">admin</option></select></div>
        <div class="campo"><label for="clareza">Clareza</label>
          <select id="clareza" name="clareza">
            <?php foreach ($niveis as $n => $rot): ?>
              <option value="<?= (int) $n ?>" <?= (int) $n === 1 ? 'selected' : '' ?>><?= (int) $n ?> · <?= e($rot) ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>
      <button class="botao botao-forte" type="submit"><?= icone_svg('usuario', 16) ?> Criar</button>
    </form>
  </section>

  <section class="bloco">
    <h2><?= icone_svg('chave', 18) ?> Sessões ativas</h2>
    <table class="tabela">
      <thead><tr><th>Usuário</th><th>Início</th><th>Expira</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($sessoes as $s): ?>
        <tr><td><code><?= e((string) $s['usuario']) ?></code></td>
          <td class="miudo"><?= e(data_br((string) $s['criada_em'], true)) ?></td>
          <td class="miudo"><?= e(data_br((string) $s['expira_em'], true)) ?></td>
          <td class="miudo"><?= e((string) $s['ip']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$sessoes): ?><tr><td colspan="4" class="miudo">Nenhuma sessão ativa.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
</section>

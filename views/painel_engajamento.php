<section class="painel-bloco">
  <h2>Pedidos de engajamento</h2>
  <p class="miudo">Cada pedido é um caminho para destravar as camadas 2, 3 e 4 de um projeto.
     Liberar emite um link de acesso temporário — e o pedido passa a <em>liberado</em>.</p>
  <div class="painel-grade3">
    <div class="painel-card"><span class="painel-n"><?= (int) $stats['total'] ?></span><span class="miudo">pedidos no total</span></div>
    <div class="painel-card"><span class="painel-n"><?= (int) $stats['novo'] ?></span><span class="miudo">novos</span></div>
    <div class="painel-card"><span class="painel-n"><?= (int) $stats['liberado'] ?></span><span class="miudo">liberados</span></div>
  </div>

  <table class="tabela">
    <thead><tr><th>#</th><th>Quando</th><th>Quem</th><th>Projeto</th><th>Nível pedido</th><th>Tipo</th><th>Estado</th><th>Ação</th></tr></thead>
    <tbody>
    <?php foreach ($pedidos as $p): ?>
      <tr>
        <td><?= (int) $p['id'] ?></td>
        <td class="miudo"><?= e(substr((string) $p['quando'], 0, 16)) ?></td>
        <td><?= e((string) $p['nome']) ?><br><span class="miudo"><?= e((string) $p['contato']) ?></span></td>
        <td class="miudo"><?= e((string) $p['projeto']) ?></td>
        <td class="miudo"><?= e(nivel_rotulo(nivel_valor((string) $p['nivel_solicitado']))) ?></td>
        <td class="miudo"><?= e((string) $p['tipo']) ?></td>
        <td><span class="selo-estado st-<?= e((string) $p['estado']) ?>"><?= e((string) $p['estado']) ?></span></td>
        <td class="acoes">
          <form method="post" action="<?= e(url_painel('/engajamento')) ?>" class="inline">
            <?= $csrf ?>
            <input type="hidden" name="acao" value="liberar">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <select name="clareza">
              <option value="2">Nível 2 · institucional</option>
              <option value="3" selected>Nível 3 · técnico</option>
              <option value="4">Nível 4 · playbook</option>
            </select>
            <input type="number" name="dias" value="30" min="1" max="365" class="mini">
            <button class="botao botao-mini" type="submit">Liberar</button>
          </form>
          <form method="post" action="<?= e(url_painel('/engajamento')) ?>" class="inline">
            <?= $csrf ?>
            <input type="hidden" name="acao" value="arquivar">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="botao botao-mini" type="submit">Arquivar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>

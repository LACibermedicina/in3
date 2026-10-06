<section class="pagina">
  <h1><?= icone_svg('pasta', 26) ?> Pedidos de detalhamento</h1>
  <p class="miudo">Liberar um pedido emite um link temporário (<code>/acesso/TOKEN</code>) que eleva a clareza do visitante
     para o projeto indicado, com prazo e limite de usos. O link é exibido uma vez, na confirmação.</p>

  <table class="tabela">
    <thead><tr><th>Quando</th><th>Quem</th><th>Contato</th><th>Projeto</th><th>Camada pedida</th><th>Mensagem</th><th>Estado</th><th>Ação</th></tr></thead>
    <tbody>
    <?php foreach ($pedidos as $pd): ?>
      <tr>
        <td class="miudo"><?= e(data_br((string) $pd['quando'], true)) ?></td>
        <td><?= e((string) $pd['nome']) ?></td>
        <td class="miudo"><?= e((string) $pd['contato']) ?></td>
        <td><?= e((string) $pd['projeto']) ?></td>
        <td><span class="chip"><?= e((string) $pd['nivel']) ?></span></td>
        <td class="miudo"><?= e(texto_limpo((string) $pd['mensagem'], 120)) ?></td>
        <td><span class="etq etq-<?= $pd['estado'] === 'novo' ? 'parcial' : ($pd['estado'] === 'liberado' ? 'aberto' : 'interno') ?>">
          <?= e((string) $pd['estado']) ?></span></td>
        <td>
          <form method="post" action="<?= e(url_painel('/pedidos')) ?>" class="form-inline">
            <?= $csrf ?><input type="hidden" name="id" value="<?= (int) $pd['id'] ?>">
            <select name="clareza" aria-label="Camada a liberar">
              <option value="2">2 · Institucional</option>
              <option value="3" selected>3 · Técnico</option>
              <option value="4">4 · Playbook</option>
            </select>
            <input name="dias" type="number" min="1" max="365" value="30" aria-label="Dias de validade" class="in-num">
            <button class="botao botao-mini" name="acao" value="liberar" type="submit"><?= icone_svg('chave', 14) ?> Liberar</button>
            <button class="botao botao-mini botao-fantasma" name="acao" value="arquivado" type="submit">Arquivar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$pedidos): ?><tr><td colspan="8" class="miudo">Nenhum pedido recebido.</td></tr><?php endif; ?>
    </tbody>
  </table>

  <section class="bloco">
    <h2><?= icone_svg('chave', 18) ?> Acessos emitidos</h2>
    <table class="tabela">
      <thead><tr><th>Emitido</th><th>Expira</th><th>Camada</th><th>Projeto</th><th>Usos</th><th>Estado</th></tr></thead>
      <tbody>
      <?php foreach ($acessos as $a): ?>
        <tr><td class="miudo"><?= e(data_br((string) $a['criado_em'], true)) ?></td>
          <td class="miudo"><?= e(data_br((string) $a['expira_em'], true)) ?></td>
          <td><span class="chip"><?= e(nivel_rotulo((int) $a['clareza'])) ?></span></td>
          <td class="miudo"><?= (int) $a['projeto_id'] ?></td>
          <td class="miudo"><?= (int) $a['usos'] ?>/<?= (int) $a['max_usos'] ?></td>
          <td><span class="etq etq-<?= $a['estado'] === 'ativo' ? 'aberto' : 'interno' ?>"><?= e((string) $a['estado']) ?></span></td></tr>
      <?php endforeach; ?>
      <?php if (!$acessos): ?><tr><td colspan="6" class="miudo">Nenhum acesso emitido.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
</section>

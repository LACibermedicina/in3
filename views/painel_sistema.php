<section class="pagina">
  <h1><?= icone_svg('escudo', 26) ?> Sistema</h1>

  <div class="kpis kpis-mini">
    <?php foreach ($contagens as $k => $v): ?>
      <div class="kpi"><span class="kpi-n"><?= (int) $v ?></span><span class="kpi-r"><?= e(str_replace('_', ' ', (string) $k)) ?></span></div>
    <?php endforeach; ?>
  </div>

  <div class="duas-colunas">
    <section class="bloco">
      <h2><?= icone_svg('codigo', 18) ?> Ambiente</h2>
      <dl class="ficha">
        <div><dt>PHP</dt><dd><?= e((string) $php) ?></dd></div>
        <div><dt>SQLite</dt><dd><?= e((string) $sqlite) ?></dd></div>
        <div><dt>Busca (FTS5)</dt><dd><?= e((string) $fts) ?></dd></div>
        <div><dt>Esquema</dt><dd><?= e((string) $versao) ?></dd></div>
        <div><dt>Banco</dt><dd><code><?= e((string) $banco) ?></code> (<?= e((string) $banco_mb) ?> MB)</dd></div>
        <div><dt>Rota do painel</dt><dd><code><?= e((string) $rota_painel) ?></code></dd></div>
        <div><dt>Conta GitHub</dt><dd><?= e((string) $conta_github) ?: '—' ?></dd></div>
        <div><dt>Fonte dos repositórios</dt><dd><?= e((string) $fonte_repos) ?></dd></div>
        <div><dt>Sincronizado em</dt><dd><?= $sincronizado_em !== '' ? e(data_br($sincronizado_em, true)) : 'nunca' ?></dd></div>
        <div><dt>Nível padrão de novos projetos</dt><dd><?= e((string) $nivel_padrao) ?></dd></div>
      </dl>
    </section>

    <section class="bloco">
      <h2><?= icone_svg('relogio', 18) ?> Manutenção</h2>
      <form method="post" action="<?= e(url_painel('/sistema')) ?>" class="form-bloco">
        <?= $csrf ?>
        <div class="campo"><label for="token">Token de leitura do GitHub</label>
          <input id="token" name="token" type="password" autocomplete="off" placeholder="ghp_… (usado só nesta chamada)">
          <p class="miudo">Sem token, a sincronização cobre apenas os repositórios públicos e marca o restante como
             <em>pendente</em>. O token nunca é gravado em banco, arquivo ou log.</p></div>
        <button class="botao botao-forte" name="acao" value="sincronizar" type="submit">
          <?= icone_svg('codigo', 16) ?> Sincronizar GitHub</button>
      </form>
      <form method="post" action="<?= e(url_painel('/sistema')) ?>" class="form-bloco">
        <?= $csrf ?>
        <button class="botao" name="acao" value="reindexar" type="submit"><?= icone_svg('busca', 16) ?> Reconstruir índice de busca</button>
      </form>
      <form method="post" action="<?= e(url_painel('/sistema')) ?>" class="form-bloco">
        <?= $csrf ?>
        <div class="campo"><label for="nivel_padrao">Nível padrão de novos projetos</label>
          <select id="nivel_padrao" name="nivel_padrao">
            <?php foreach (IN3_NIVEL_ROTULO as $n => $rot): ?>
              <option value="<?= e(nivel_chave((int) $n)) ?>"
                <?= (string) configuracao('nivel_padrao', 'institucional_roadmap') === nivel_chave((int) $n) ? 'selected' : '' ?>>
                <?= (int) $n ?> · <?= e($rot) ?></option>
            <?php endforeach; ?>
          </select></div>
        <button class="botao" name="acao" value="politica" type="submit">Salvar política</button>
      </form>
    </section>
  </div>

  <section class="bloco">
    <h2><?= icone_svg('camadas', 18) ?> Tabelas</h2>
    <div class="nuvem-chips">
      <?php foreach ($tabelas as $t): ?>
        <span class="chip chip-tec"><?= e((string) $t['name']) ?> <span class="chip-n"><?= (int) $t['colunas'] ?></span></span>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="bloco">
    <h2><?= icone_svg('escudo', 18) ?> Auditoria (30 últimos)</h2>
    <table class="tabela">
      <thead><tr><th>Quando</th><th>Ação</th><th>Entidade</th><th>Detalhe</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($auditoria as $a): ?>
        <tr><td class="miudo"><?= e(data_br((string) $a['quando'], true)) ?></td>
          <td><code><?= e((string) $a['acao']) ?></code></td>
          <td class="miudo"><?= e((string) $a['entidade']) ?><?= $a['entidade_id'] ? '#' . (int) $a['entidade_id'] : '' ?></td>
          <td class="miudo"><?= e((string) $a['detalhe']) ?></td>
          <td class="miudo"><?= e((string) $a['ip']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</section>

<section class="pagina">
  <h1><?= icone_svg('grafo', 26) ?> Visão geral</h1>
  <div class="kpis">
    <div class="kpi"><span class="kpi-n"><?= (int) $total ?></span><span class="kpi-r">projetos no acervo</span></div>
    <div class="kpi"><span class="kpi-n"><?= (int) $publicos ?></span><span class="kpi-r">publicados</span></div>
    <div class="kpi"><span class="kpi-n"><?= (int) $internos ?></span><span class="kpi-r">internos</span></div>
    <div class="kpi"><span class="kpi-n"><?= (int) $cobertura['total'] ?></span><span class="kpi-r">repositórios inventariados</span></div>
    <div class="kpi"><span class="kpi-n"><?= (int) $cobertura['mapeados'] ?></span><span class="kpi-r">mapeados em projetos</span></div>
    <div class="kpi <?= $cobertura['completo'] ? '' : 'kpi-alerta' ?>">
      <span class="kpi-n"><?= count($cobertura['orfaos']) ?></span><span class="kpi-r">sem projeto</span></div>
    <div class="kpi"><span class="kpi-n"><?= (int) $pedidos_novos ?></span><span class="kpi-r">pedidos novos</span></div>
    <div class="kpi"><span class="kpi-n"><?= (int) $acessos_ativos ?></span><span class="kpi-r">acessos ativos</span></div>
  </div>

  <div class="duas-colunas">
    <section class="bloco">
      <h2><?= icone_svg('camadas', 18) ?> Distribuição por nível de divulgação</h2>
      <ul class="barras">
        <?php foreach ($por_nivel as $chave => $n): ?>
          <li><span class="barra-rot"><?= e(nivel_rotulo(nivel_valor((string) $chave))) ?></span>
            <span class="barra-trilha"><span class="barra-preenche" style="width:<?= $total > 0 ? (int) round($n / max(1, $total) * 100) : 0 ?>%"></span></span>
            <span class="barra-n"><?= (int) $n ?></span></li>
        <?php endforeach; ?>
      </ul>
      <p class="miudo">Fonte dos repositórios: <strong><?= e($fonte_repos) ?></strong>
        <?= $sincronizado_em !== '' ? '· sincronizado em ' . e(data_br($sincronizado_em, true)) : '' ?>
        · busca: <strong><?= e($fts) ?></strong> · esquema: <?= e($versao) ?></p>
    </section>

    <section class="bloco">
      <h2><?= icone_svg('codigo', 18) ?> Cobertura de repositórios</h2>
      <p class="miudo">Todo repositório do inventário deve estar em ao menos um projeto — esta lista é o passivo de curadoria.</p>
      <ul class="lista-check">
        <?php foreach ($cobertura['visibilidade'] as $v): ?>
          <li><?= icone_svg($v['visibilidade'] === 'publico' ? 'olho' : 'olho_fechado', 15) ?>
            <strong><?= e((string) $v['visibilidade']) ?></strong>: <?= (int) $v['n'] ?></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($cobertura['orfaos']): ?>
        <details open><summary><?= count($cobertura['orfaos']) ?> repositório(s) sem projeto</summary>
          <ul class="lista-miuda">
            <?php foreach ($cobertura['orfaos'] as $r): ?>
              <li><code><?= e((string) $r['nome']) ?></code>
                <span class="chip"><?= e((string) $r['visibilidade']) ?></span>
                <span class="miudo"><?= e(texto_limpo((string) $r['descricao'], 60)) ?></span></li>
            <?php endforeach; ?>
          </ul>
        </details>
      <?php else: ?>
        <p class="miudo"><?= icone_svg('escudo', 15) ?> Inventário 100% mapeado.</p>
      <?php endif; ?>
    </section>
  </div>

  <section class="bloco">
    <h2><?= icone_svg('relogio', 18) ?> Acessos recentes</h2>
    <table class="tabela">
      <thead><tr><th>Quando</th><th>Rota</th><th>Papel</th><th>Termo</th><th>Result.</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($log as $l): ?>
        <tr><td><?= e(data_br((string) $l['quando'], true)) ?></td><td><code><?= e((string) $l['rota']) ?></code></td>
          <td><span class="chip"><?= e((string) $l['papel']) ?></span></td><td><?= e((string) $l['termo']) ?: '—' ?></td>
          <td><?= (int) $l['resultado'] ?></td><td class="miudo"><?= e((string) $l['ip']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$log): ?><tr><td colspan="6" class="miudo">Sem registros.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="bloco">
    <h2><?= icone_svg('escudo', 18) ?> Auditoria</h2>
    <table class="tabela">
      <thead><tr><th>Quando</th><th>Ação</th><th>Entidade</th><th>Detalhe</th></tr></thead>
      <tbody>
      <?php foreach ($auditoria as $a): ?>
        <tr><td><?= e(data_br((string) $a['quando'], true)) ?></td><td><code><?= e((string) $a['acao']) ?></code></td>
          <td><?= e((string) $a['entidade']) ?><?= $a['entidade_id'] ? '#' . (int) $a['entidade_id'] : '' ?></td>
          <td class="miudo"><?= e((string) $a['detalhe']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</section>

<section class="pagina">
  <h1><?= icone_svg('codigo', 26) ?> Repositórios do acervo</h1>
  <p class="miudo">Fonte: <strong><?= e($fonte) ?></strong>
     <?= $sincronizado_em !== '' ? '· sincronizado em ' . e(data_br($sincronizado_em, true)) : '· execute a sincronização em Sistema' ?>.
     Visibilidade <em>pendente</em> significa: item declarado na curadoria e ainda não confirmado pela API.</p>

  <div class="kpis kpis-mini">
    <div class="kpi"><span class="kpi-n"><?= (int) $cobertura['total'] ?></span><span class="kpi-r">total</span></div>
    <div class="kpi"><span class="kpi-n"><?= (int) $cobertura['mapeados'] ?></span><span class="kpi-r">em projetos</span></div>
    <div class="kpi <?= $cobertura['completo'] ? '' : 'kpi-alerta' ?>"><span class="kpi-n"><?= count($cobertura['orfaos']) ?></span><span class="kpi-r">sem projeto</span></div>
  </div>

  <table class="tabela">
    <thead><tr><th>Nome</th><th>Visibilidade</th><th>Linguagem</th><th>Descrição</th><th>Atualizado</th><th>Commits</th><th>Projetos</th></tr></thead>
    <tbody>
    <?php foreach ($repos as $r): ?>
      <tr>
        <td><code><?= e((string) $r['nome']) ?></code>
          <?= (int) $r['fork'] === 1 ? '<span class="chip">fork</span>' : '' ?></td>
        <td>
          <span class="etq etq-<?= $r['visibilidade'] === 'publico' ? 'aberto' : ($r['visibilidade'] === 'privado' ? 'interno' : 'parcial') ?>">
            <?= icone_svg($r['visibilidade'] === 'publico' ? 'olho' : 'olho_fechado', 13) ?> <?= e((string) $r['visibilidade']) ?></span></td>
        <td class="miudo"><?= e((string) ($r['linguagem'] !== '' ? $r['linguagem'] : '—')) ?></td>
        <td class="miudo"><?= e(texto_limpo((string) $r['descricao'], 90)) ?: '<em>sem descrição no GitHub</em>' ?></td>
        <td class="miudo"><?= e(data_br((string) $r['gh_atualizado_em'])) ?></td>
        <td class="miudo"><?= (int) ($r['commit_total'] ?? 0) ?></td>
        <td class="miudo"><?= (int) $r['n_projetos'] === 0 ? '<span class="etq etq-interno">0</span>' : (int) $r['n_projetos'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>

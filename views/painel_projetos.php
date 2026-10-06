<section class="pagina">
  <h1><?= icone_svg('camadas', 26) ?> Projetos</h1>
  <p class="miudo">Tudo que o público vê sai da coluna <em>mostrar ao público</em> mais o teto de nível.
     A clareza de quem lê é o segundo filtro — o menor dos dois manda.</p>
  <p><a class="botao botao-forte" href="<?= e(url_painel('/projeto/0')) ?>"><?= icone_svg('pasta', 16) ?> Novo projeto</a></p>

  <table class="tabela tabela-proj">
    <thead><tr><th>Título</th><th>Área</th><th>Situação</th><th>Público</th><th>Link repo</th>
      <th>Nível de divulgação</th><th>Repos</th><th>Ações</th></tr></thead>
    <tbody>
    <?php foreach ($projetos as $p): ?>
      <tr>
        <td><span aria-hidden="true"><?= e((string) $p['emoji']) ?></span>
          <a href="<?= e(url('/p/' . $p['slug'])) ?>" rel="noopener"><?= e((string) $p['titulo']) ?></a>
          <br><code class="miudo">/p/<?= e((string) $p['slug']) ?></code></td>
        <td class="miudo"><?= e((string) $p['categoria']) ?></td>
        <td class="miudo"><?= e((string) $p['status']) ?></td>
        <td><?= (int) $p['mostrar_ao_publico'] === 1
          ? '<span class="etq etq-aberto">' . icone_svg('olho', 13) . ' público</span>'
          : '<span class="etq etq-interno">' . icone_svg('olho_fechado', 13) . ' interno</span>' ?></td>
        <td><?= (int) $p['mostrar_link_repo'] === 1 ? 'sim' : 'não' ?></td>
        <td><span class="etq etq-parcial"><?= icone_svg('camadas', 13) ?>
          <?= e(nivel_rotulo(nivel_valor((string) $p['nivel_divulgacao']))) ?></span></td>
        <td class="miudo"><?= count($p['_repos']) ?></td>
        <td><a class="botao botao-mini" href="<?= e(url_painel('/projeto/' . (int) $p['id'])) ?>">Editar</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>

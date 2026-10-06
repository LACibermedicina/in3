<section class="pagina">
  <h1><?= icone_svg('camadas', 24) ?> <?= $p['id'] ? 'Editar' : 'Novo' ?> projeto</h1>
  <p class="miudo">Campos marcados <strong>(pendente)</strong> não são publicados — é a regra do PLAYBOOK.
     Deixe em branco o que ainda não é verificável.</p>

  <form class="form-bloco form-projeto" method="post" action="<?= e($url_salvar) ?>">
    <?= $csrf ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">

    <fieldset><legend>Identidade</legend>
      <div class="campo"><label for="titulo">Título *</label>
        <input id="titulo" name="titulo" required maxlength="160" value="<?= e((string) $p['titulo']) ?>"></div>
      <div class="linha-campos">
        <div class="campo"><label for="emoji">Emoji</label>
          <input id="emoji" name="emoji" maxlength="8" value="<?= e((string) $p['emoji']) ?>"></div>
        <div class="campo"><label for="icone">Ícone (SVG local)</label>
          <select id="icone" name="icone">
            <?php foreach ($icones as $ic): ?>
              <option value="<?= e($ic) ?>" <?= $p['icone'] === $ic ? 'selected' : '' ?>><?= e($ic) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="previa-ico" id="previa-ico"><?= icone_svg((string) $p['icone'], 30) ?></span></div>
        <div class="campo"><label for="ordem">Ordem</label>
          <input id="ordem" name="ordem" type="number" min="0" max="9999" value="<?= (int) $p['ordem'] ?>"></div>
      </div>
      <div class="linha-campos">
        <div class="campo"><label for="categoria">Área</label>
          <input id="categoria" name="categoria" list="cats" maxlength="80" value="<?= e((string) $p['categoria']) ?>">
          <datalist id="cats">
            <?php foreach (['Telemedicina & Saúde Digital','Fiscal & Finanças','Comércio & Vitrine','Educação Médica','Instrumentação & Sensores','Pesquisa & IA','Governança & Infra'] as $c): ?>
              <option value="<?= e($c) ?>"></option><?php endforeach; ?>
          </datalist></div>
        <div class="campo"><label for="status">Situação</label>
          <input id="status" name="status" list="sts" maxlength="40" value="<?= e((string) $p['status']) ?>">
          <datalist id="sts">
            <?php foreach (['ativo','em incubação','protótipo','pesquisa','entregue','backlog','arquivado','infra'] as $s): ?>
              <option value="<?= e($s) ?>"></option><?php endforeach; ?>
          </datalist></div>
      </div>
    </fieldset>

    <fieldset><legend>Liberação</legend>
      <div class="linha-campos">
        <label class="chave"><input type="checkbox" name="mostrar_ao_publico" value="1" <?= (int) $p['mostrar_ao_publico'] === 1 ? 'checked' : '' ?>>
          Mostrar ao público</label>
        <label class="chave"><input type="checkbox" name="mostrar_link_repo" value="1" <?= (int) $p['mostrar_link_repo'] === 1 ? 'checked' : '' ?>>
          Expor endereços dos repositórios</label>
      </div>
      <div class="campo"><label for="nivel_divulgacao">Teto de divulgação</label>
        <select id="nivel_divulgacao" name="nivel_divulgacao">
          <?php foreach (IN3_NIVEL_ROTULO as $n => $rot): ?>
            <option value="<?= e(nivel_chave((int) $n)) ?>"
              <?= nivel_valor((string) $p['nivel_divulgacao']) === (int) $n ? 'selected' : '' ?>>
              <?= (int) $n ?> · <?= e($rot) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="miudo">1 · roadmap institucional (público) · 2 · institucional · 3 · técnico · 4 · playbook completo.</p></div>
    </fieldset>

    <fieldset><legend>Camada 1 · roadmap institucional (sempre pública quando o projeto é público)</legend>
      <div class="campo"><label for="resumo">Resumo</label>
        <textarea id="resumo" name="resumo" rows="3" maxlength="600"><?= e((string) $p['resumo']) ?></textarea></div>
      <div class="campo"><label for="marcos">Marcos — uma linha por marco: <code data-AAAA-MM-DD>AAAA-MM-DD</code> | texto</label>
        <textarea id="marcos" name="marcos" rows="5" placeholder="2026-01-15 | Primeira versão no ar"><?= e($marcos_texto) ?></textarea></div>
    </fieldset>

    <fieldset><legend>Camada 2 · institucional</legend>
      <div class="campo"><label for="publico_alvo">Público-alvo</label>
        <textarea id="publico_alvo" name="publico_alvo" rows="2" maxlength="400"><?= e((string) $p['publico_alvo']) ?></textarea></div>
      <div class="campo"><label for="problema">Problema</label>
        <textarea id="problema" name="problema" rows="2" maxlength="600"><?= e((string) $p['problema']) ?></textarea></div>
      <div class="campo"><label for="solucao">Solução</label>
        <textarea id="solucao" name="solucao" rows="2" maxlength="600"><?= e((string) $p['solucao']) ?></textarea></div>
      <div class="campo"><label for="como_funciona">Como funciona (linguagem humana)</label>
        <textarea id="como_funciona" name="como_funciona" rows="3" maxlength="700"><?= e((string) $p['como_funciona']) ?></textarea></div>
      <div class="campo"><label for="validacao">Validação</label>
        <textarea id="validacao" name="validacao" rows="2" maxlength="500"><?= e((string) $p['validacao']) ?></textarea></div>
    </fieldset>

    <fieldset><legend>Camada 3 · técnico</legend>
      <div class="campo"><label for="arquitetura">Arquitetura</label>
        <textarea id="arquitetura" name="arquitetura" rows="3" maxlength="900"><?= e((string) $p['arquitetura']) ?></textarea></div>
      <div class="campo"><label for="integracoes">Integrações</label>
        <textarea id="integracoes" name="integracoes" rows="2" maxlength="600"><?= e((string) $p['integracoes']) ?></textarea></div>
      <div class="campo"><label for="tecnologias">Tecnologias (separadas por vírgula)</label>
        <input id="tecnologias" name="tecnologias" maxlength="600" value="<?= e($tecnologias) ?>"></div>
    </fieldset>

    <fieldset><legend>Camada 4 · playbook completo</legend>
      <div class="campo"><label for="privacidade">Privacidade e LGPD</label>
        <textarea id="privacidade" name="privacidade" rows="3" maxlength="800"><?= e((string) $p['privacidade']) ?></textarea></div>
      <div class="campo"><label for="riscos">Riscos conhecidos</label>
        <textarea id="riscos" name="riscos" rows="3" maxlength="800"><?= e((string) $p['riscos']) ?></textarea></div>
      <div class="campo"><label for="playbook_ref">Referência no playbook</label>
        <input id="playbook_ref" name="playbook_ref" maxlength="160" value="<?= e((string) $p['playbook_ref']) ?>"></div>
      <div class="campo"><label for="notas_internas">Notas internas (nunca publicadas)</label>
        <textarea id="notas_internas" name="notas_internas" rows="3" maxlength="1200"><?= e((string) $p['notas_internas']) ?></textarea></div>
    </fieldset>

    <fieldset><legend>Repositórios do acervo</legend>
      <p class="miudo"><?= count($repos) ?> repositórios inventariados. Marcar aqui define o vínculo projeto ↔ repositório
         (o que aparece em público depende de <em>expor endereços dos repositórios</em>).</p>
      <div class="grade-repos">
        <?php foreach ($repos as $r): ?>
          <label class="chave repo-op">
            <input type="checkbox" name="repos[]" value="<?= e((string) $r['nome']) ?>"
              <?= in_array((int) $r['id'], $repos_do_projeto, true) ? 'checked' : '' ?>>
            <code><?= e((string) $r['nome']) ?></code>
            <span class="chip"><?= e((string) $r['visibilidade']) ?></span>
            <?= (int) $r['fork'] === 1 ? '<span class="chip">fork</span>' : '' ?>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <p><button class="botao botao-forte" type="submit" formaction="<?= e($url_salvar) ?>">
      <?= icone_svg('escudo', 16) ?> Salvar projeto</button>
      <a class="botao" href="<?= e(url_painel('/projetos')) ?>">Voltar</a></p>
  </form>
</section>

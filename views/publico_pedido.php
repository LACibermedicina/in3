<section class="pagina">
  <h1><?= icone_svg('chave', 26) ?> Pedir detalhamento</h1>
  <p>Os projetos da IN³ têm quatro camadas. A camada 1 (roadmap institucional) é sempre pública.
     As camadas 2 (institucional), 3 (técnica) e 4 (playbook completo) são liberadas sob pedido, com prazo e escopo definidos.</p>

  <?php if ($erros): ?>
    <div class="aviso aviso-erro"><ul><?php foreach ($erros as $er) : ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form class="form-bloco" method="post" action="<?= e(url('/pedido')) ?>">
    <?= $csrf ?>
    <div class="campo"><label for="nome">Seu nome *</label><input id="nome" name="nome" required minlength="3" maxlength="120"></div>
    <div class="campo"><label for="contato">E-mail ou telefone *</label><input id="contato" name="contato" required maxlength="160"
        placeholder="voce@dominio.com ou +55 11 90000-0000"></div>
    <div class="campo"><label for="projeto">Projeto</label>
      <input id="projeto" name="projeto" list="lista-projetos" maxlength="120"
             value="<?= e(entrada('projeto')) ?>" placeholder="Escolha ou escreva o nome">
      <datalist id="lista-projetos">
        <?php foreach ($projetos as $p): ?><option value="<?= e((string) $p['titulo']) ?>"></option><?php endforeach; ?>
      </datalist>
    </div>
    <div class="campo"><label for="nivel">Camada solicitada</label>
      <select id="nivel" name="nivel">
        <option value="institucional">2 · Institucional (problema, solução, público-alvo)</option>
        <option value="tecnico">3 · Técnico (arquitetura, integrações, repositórios)</option>
        <option value="playbook">4 · Playbook completo (privacidade, riscos, validação)</option>
      </select>
    </div>
    <div class="campo"><label for="mensagem">O que você precisa *</label>
      <textarea id="mensagem" name="mensagem" rows="5" required minlength="10" maxlength="1200"
        placeholder="Descreva o contexto: uso clínico, avaliação técnica, pesquisa, integração…"></textarea></div>
    <p class="miudo"><?= icone_svg('escudo', 14) ?> Guardamos apenas o necessário para responder: nome, contato, projeto e mensagem.
       Nenhum dado de acesso é exibido em tela ou em log.</p>
    <button class="botao botao-forte" type="submit"><?= icone_svg('seta', 16) ?> Enviar pedido</button>
  </form>

  <section class="bloco">
    <h2><?= icone_svg('camadas', 18) ?> O que cada camada entrega</h2>
    <dl class="ficha">
      <div><dt>1 · Roadmap institucional</dt><dd>Aberto no site: identidade do projeto, área, situação e marcos públicos.</dd></div>
      <div><dt>2 · Institucional</dt><dd>Problema, solução, público-alvo, como funciona em linguagem humana e validação.</dd></div>
      <div><dt>3 · Técnico</dt><dd>Arquitetura, integrações e repositórios do acervo (quando a curadoria libera o link).</dd></div>
      <div><dt>4 · Playbook completo</dt><dd>Ficha inteira: privacidade e LGPD, riscos conhecidos, critérios de validação e referência no playbook.</dd></div>
    </dl>
  </section>
</section>

<section class="pagina">
  <h1><?= icone_svg('escudo', 24) ?> Instalação necessária</h1>
  <p><?= e((string) $motivo) ?></p>
  <p><a class="botao botao-forte" href="<?= e($urlInstalar) ?>">Abrir o instalador</a></p>
  <p class="miudo">Ou, na linha de comando:
    <code>php public/index.php --instalar --usuario=SEU_USUARIO --senha=SUA_SENHA</code></p>
</section>

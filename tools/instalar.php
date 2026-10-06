<?php
declare(strict_types=1);
/**
 * IN³ · instalação por linha de comando (usado pelo install.sh).
 * Aplica o esquema, define configurações iniciais e informa o próximo passo.
 *
 *   php tools/instalar.php
 */

require_once dirname(__DIR__) . '/src/nucleo.php';

fwrite(STDOUT, "IN³ · instalador\n");
foreach (migrar() as $r) {
    fwrite(STDOUT, "  ✓ {$r}\n");
}

$host = (string) cfg('IN3_HOST', 'in.m3d.pro');
configurar('host_publico', $host);
configurar('slogan', 'O que entra na m3d, sai ao cubo.');
if (configuracao('nivel_padrao') === null) {
    configurar('nivel_padrao', 'institucional_roadmap');
}
if (configuracao('conta_github') === null) {
    configurar('conta_github', (string) cfg('IN3_CONTA', 'LACibermedicina'));
}

fwrite(STDOUT, '  ✓ banco em ' . (string) cfg('IN3_BANCO', IN3_DADOS . '/in3.db') . "\n");
fwrite(STDOUT, '  ✓ host público: ' . $host . ' · rota do painel: ' . rota_painel() . "\n");
fwrite(STDOUT, '  ✓ busca: ' . (fts_ligado() ? 'FTS5' : 'LIKE') . "\n");

if (!existe_admin()) {
    fwrite(STDOUT, "\n  ! Nenhum administrador existe ainda. Crie o primeiro com:\n");
    fwrite(STDOUT, "      php tools/senha.php --usuario=SEU_USUARIO --criar --papel=admin --clareza=4\n");
    fwrite(STDOUT, "    ou abra o instalador no navegador: /instalar\n");
    fwrite(STDOUT, "    (enquanto não houver administrador, o site público responde com o aviso de instalação)\n\n");
} else {
    fwrite(STDOUT, "\n  ✓ administrador presente. Próximos passos:\n");
    fwrite(STDOUT, "      php tools/sincronizar.php   # inventário GitHub\n");
    fwrite(STDOUT, "      php tools/semear.php        # acervo + curadoria\n");
    fwrite(STDOUT, "      php tools/verificar.php     # prova de privacidade\n\n");
}

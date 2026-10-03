<?php
declare(strict_types=1);
/**
 * IN³ · senha.php — cria usuários e redefine senhas pela linha de comando.
 * A senha nunca é exibida nem registrada em log.
 *   php tools/senha.php --usuario=fulano --senha='...' [--papel=admin --clareza=4]
 *   php tools/senha.php --usuario=fulano            (redefine: pergunta a senha)
 */
require_once dirname(__DIR__) . '/src/nucleo.php';

$op = [];
foreach ($argv as $a) {
    if (preg_match('/^--([a-z_]+)(?:=(.*))?$/i', $a, $m)) {
        $op[$m[1]] = $m[2] ?? true;
    }
}
$usuario = trim((string) ($op['usuario'] ?? ''));
if ($usuario === '') {
    fwrite(STDERR, "Uso: php tools/senha.php --usuario=NOME [--senha=SENHA] [--papel=root|admin|curador|leitor] [--clareza=1..4]\n");
    exit(1);
}
if (!banco_existe()) {
    fwrite(STDERR, "  ! banco ausente — rode primeiro: php tools/instalar.php\n");
    exit(1);
}

$senha = (string) ($op['senha'] ?? '');
if ($senha === '') {
    fwrite(STDOUT, "nova senha para '{$usuario}': ");
    $senha = trim((string) fgets(STDIN));
}
if ($erro = senha_forte($senha)) {
    fwrite(STDERR, '  ✖ ' . $erro . "\n");
    exit(1);
}

$existe = um('SELECT * FROM usuarios WHERE usuario = :u', [':u' => $usuario]);
if ($existe) {
    definer_senha_id((int) $existe['id'], $senha);
    fwrite(STDOUT, "  ✓ senha de '{$usuario}' redefinida (hash atualizado, sessões antigas seguem válidas até expirar)\n");
    auditar('senha_redefinida_cli', $usuario);
    exit(0);
}

$papel = in_array((string) ($op['papel'] ?? 'leitor'), IN3_PAPEIS, true) ? (string) $op['papel'] : 'leitor';
$clareza = max(1, min(4, (int) ($op['clareza'] ?? 1)));
criar_usuario($usuario, $senha, $papel, $clareza, (string) ($op['nome'] ?? ''), (string) ($op['email'] ?? ''), false);
fwrite(STDOUT, "  ✓ usuário '{$usuario}' criado (papel {$papel} · clareza {$clareza})\n");
auditar('usuario_criado_cli', $usuario, 'papel=' . $papel);

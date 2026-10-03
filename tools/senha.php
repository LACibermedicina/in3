<?php
declare(strict_types=1);
/**
 * IN³ · redação de senha pela linha de comando.
 *
 * Uso:
 *   php tools/senha.php --usuario=lucas              (pergunta a senha sem eco)
 *   php tools/senha.php --usuario=lucas --criar --papel=admin --clareza=4
 *
 * A senha é lida do terminal (nunca como argumento, para não entrar no
 * histórico do shell) e gravada apenas como hash.
 */

require_once dirname(__DIR__) . '/src/nucleo.php';

$op = [];
foreach ($argv as $a) {
    if (preg_match('/^--([a-z0-9\-]+)(=?)(.*)$/', $a, $m)) {
        $op[$m[1]] = $m[3] !== '' ? $m[3] : '1';
    }
}

$usuario = (string) ($op['usuario'] ?? '');
if ($usuario === '') {
    fwrite(STDERR, "Uso: php tools/senha.php --usuario=NOME [--criar --papel=admin --clareza=4]\n");
    exit(1);
}

function pergunta_senha(): string
{
    fwrite(STDOUT, 'Nova senha: ');
    $primeira = '';
    if (PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec') && @shell_exec('command -v stty') !== null) {
        @shell_exec('stty -echo');
        $primeira = (string) fgets(STDIN);
        @shell_exec('stty echo');
        fwrite(STDOUT, "\n");
    } else {
        $primeira = (string) fgets(STDIN);
    }
    return rtrim($primeira, "\r\n");
}

$senha = pergunta_senha();
if (mb_strlen($senha) < 10) {
    fwrite(STDERR, "✖ A senha deve ter ao menos 10 caracteres.\n");
    exit(1);
}
fwrite(STDOUT, 'Repita a senha: ');
$conf = rtrim((string) fgets(STDIN), "\r\n");
if ($conf !== '' && $conf !== $senha) {
    fwrite(STDERR, "✖ As senhas não conferem.\n");
    exit(1);
}

$existe = um('SELECT * FROM usuarios WHERE usuario = :u', [':u' => $usuario]);
if ($existe) {
    executa('UPDATE usuarios SET senha_hash = :h WHERE id = :i', [
        ':h' => password_hash($senha, PASSWORD_DEFAULT), ':i' => $existe['id'],
    ]);
    executa('DELETE FROM sessoes WHERE usuario_id = :i', [':i' => $existe['id']]);
    auditar('senha_cli', 'usuarios', (int) $existe['id']);
    fwrite(STDOUT, "  ✓ senha redefinida para '{$usuario}'. Sessões encerradas.\n");
    exit(0);
}

if (!isset($op['criar'])) {
    fwrite(STDERR, "✖ usuário inexistente. Use --criar para criá-lo agora.\n");
    exit(1);
}
$papel = in_array((string) ($op['papel'] ?? 'leitor'), ['admin', 'curador', 'leitor'], true) ? (string) $op['papel'] : 'leitor';
$clareza = max(1, min(4, (int) ($op['clareza'] ?? 1)));
criar_usuario($usuario, $senha, $papel, $clareza, (string) ($op['nome'] ?? ''), (string) ($op['email'] ?? ''));
fwrite(STDOUT, "  ✓ usuário '{$usuario}' criado (papel {$papel}, clareza {$clareza}).\n");

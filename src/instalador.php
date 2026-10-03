<?php
declare(strict_types=1);
/**
 * =====================================================================
 * IN³ · Instalador — cria o banco, o acervo e a conta root.
 * A senha nunca fica no código: vem do prompt, do ambiente ou do
 * argumento --senha, e é gravada só como hash bcrypt.
 * =====================================================================
 */

function instalador_cli(array $argv): void
{
    $op = [];
    foreach ($argv as $a) {
        if (preg_match('/^--([a-z_]+)(?:=(.*))?$/i', $a, $m)) {
            $op[$m[1]] = $m[2] ?? true;
        }
    }

    fwrite(STDOUT, "\n=== IN³ · INcubator " . IN3_VERSAO . " — instalação ===\n\n");

    criar_esquema();
    gravar_config('esquema_versao', IN3_VERSAO);
    gravar_config('instalado_em', agora());
    fwrite(STDOUT, "  ✓ banco criado: data/in3.db (SQLite + WAL)\n");

    /* ------------------------------------------------------- inventário */
    $origem = IN3_RAIZ . '/data/semear';
    $semeados = 0;
    foreach (['github.repos.cache.json' => true, 'github.readmes.cache.json' => false] as $arq => $obrig) {
        if (is_file($origem . '/' . $arq)) {
            $semeados++;
        } elseif ($obrig) {
            fwrite(STDOUT, "  ! {$arq} ausente em data/semear — o acervo usará a semente local.\n");
        }
    }

    /* ---------------------------------------------------------- acervo */
    require IN3_RAIZ . '/tools/semear_lib.php';
    $r = semear_acervo();
    fwrite(STDOUT, '  ✓ ' . (int) $r['projetos'] . " projeto(s) no acervo\n");
    fwrite(STDOUT, '  ✓ ' . (int) $r['repos'] . " repositório(s) inventariado(s)\n");
    fwrite(STDOUT, '  ✓ ' . (int) $r['publicos'] . " projeto(s) publicado(s) · " . (int) $r['internos'] . " interno(s)\n");

    /* ----------------------------------------------- conta root (arcano) */
    $usuario = (string) ($op['usuario'] ?? 'arcano');
    $senha = (string) ($op['senha'] ?? '');
    if ($senha === '') {
        $doAmbiente = cfg('IN3_ROOT_SENHA', '');
        if ($doAmbiente !== '') {
            $senha = $doAmbiente;
        } elseif (defined('STDIN') && stream_isatty(STDIN)) {
            fwrite(STDOUT, "\n  Defina a senha do usuário '{$usuario}'.\n");
            fwrite(STDOUT, "  (Enter em branco usa a senha de demonstração 'arcano' com troca obrigatória no 1º acesso)\n");
            fwrite(STDOUT, '  senha: ');
            $senha = trim((string) fgets(STDIN));
        }
    }
    $demo = false;
    if ($senha === '') {
        $senha = 'arcano';
        $demo = true;
    }

    $existe = um('SELECT id FROM usuarios WHERE usuario = :u', [':u' => $usuario]);
    if ($existe) {
        if (!empty($op['senha'])) {
            definir_senha($usuario, $senha);
            fwrite(STDOUT, "  ✓ senha de '{$usuario}' redefinida\n");
        } else {
            fwrite(STDOUT, "  · usuário '{$usuario}' já existia (nada alterado)\n");
        }
    } else {
        criar_usuario($usuario, $senha, 'root', 4, 'Administrador root', '', true);
        fwrite(STDOUT, "  ✓ conta root '{$usuario}' criada (papel root · clareza 4 · permissão total)\n");
    }
    if ($demo) {
        fwrite(STDOUT, "  ! SENHA DE DEMONSTRAÇÃO ATIVA ('arcano'). O painel exige troca no primeiro acesso.\n");
    } else {
        fwrite(STDOUT, "  ✓ senha gravada como hash bcrypt (custo 12) — nada em texto puro\n");
    }

    gravar_config('sincronizado_em', agora());
    fwrite(STDOUT, "\n  Próximos passos:\n");
    fwrite(STDOUT, "    php tools/sincronizar.php --conta=LACibermedicina [--token=ghp_xxx]\n");
    fwrite(STDOUT, "    php tools/semear.php\n");
    fwrite(STDOUT, "    php tools/verificar.php\n");
    fwrite(STDOUT, "    php -S 127.0.0.1:8787 -t public public/index.php\n");
    fwrite(STDOUT, '    painel: /' . rota_painel() . "\n\n");
}

<?php
declare(strict_types=1);
/**
 * IN³ · instalador (duas etapas, roda no navegador e também no CLI).
 *
 * Etapa 1  · ambiente: aplica o esquema, mostra o que foi detectado.
 * Etapa 2  · administrador: cria o primeiro admin.
 *
 * Nada de senha padrão. Nada de usuário padrão. Se ninguém passar pelas duas
 * etapas, o site público nem sobe — as rotas normais só respondem depois que
 * existe um administrador.
 */

function instalador_estado(): array
{
    $checks = [];
    $checks[] = ['PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '8.1.0', '>='), 'requer 8.1 ou superior'];
    $ext = ['pdo_sqlite' => extension_loaded('pdo_sqlite'), 'sqlite3' => extension_loaded('sqlite3'), 'mbstring' => extension_loaded('mbstring'), 'json' => extension_loaded('json')];
    foreach ($ext as $nome => $ok) {
        $checks[] = ['extensão ' . $nome, $ok, $ok ? 'presente' : 'instale php-' . str_replace('_', '-', $nome)];
    }
    $dirDados = IN3_DADOS;
    $checks[] = ['diretório data/ gravável', is_writable(is_dir($dirDados) ? $dirDados : IN3_RAIZ), $dirDados];
    $checks[] = ['data/schema.sql presente', is_file($dirDados . '/schema.sql'), 'necessário para criar o banco'];
    return ['checks' => $checks, 'ok' => !in_array(false, array_column($checks, 1), true)];
}

function instalador_rota(string $caminho): never
{
    $estado = instalador_estado();
    $erros = [];

    if (metodo() === 'POST') {
        csrf_verifica();
        $acao = entrada('acao');
        if ($acao === 'esquema') {
            if (!$estado['ok']) {
                $erros[] = 'Resolva os itens vermelhos antes de criar o banco.';
            } else {
                try {
                    foreach (migrar() as $r) {
                        auditar('instalacao', 'esquema', null, $r);
                    }
                    configurar('host_publico', (string) cfg('IN3_HOST', 'in.m3d.pro'));
                    configurar('slogan', 'O que entra na m3d, sai ao cubo.');
                    configurar('manifesto', (string) cfg('IN3_MANIFESTO', 'Portfólio vivo de pesquisa, engenharia e saúde digital.'));
                    redirecionar(url('/instalar/passo2'));
                } catch (Throwable $e) {
                    $erros[] = 'Falha ao aplicar o esquema: ' . $e->getMessage();
                }
            }
        } elseif ($acao === 'admin') {
            $usuario = entrada('usuario');
            $senha = (string) ($_POST['senha'] ?? '');
            $conf = (string) ($_POST['senha2'] ?? '');
            if ($senha !== $conf) {
                $erros[] = 'As duas senhas não conferem.';
            } else {
                try {
                    criar_usuario($usuario, $senha, 'admin', 4, entrada('nome'), entrada('email'));
                    configurar('conta_github', entrada('conta_github', 'LACibermedicina'));
                    flush();
                    redirecionar(url('/instalar/concluido'));
                } catch (Throwable $e) {
                    $erros[] = $e->getMessage();
                }
            }
        }
    }

    $passo = 1;
    if (str_contains($caminho, 'passo2')) {
        $passo = 2;
    }
    if (str_contains($caminho, 'concluido')) {
        $passo = 3;
    }

    cabecalhos_seguranca(false);
    echo layout_painel('Instalação · passo ' . $passo, view('painel_instalar.php', [
        'passo' => $passo,
        'estado' => $estado,
        'erros' => $erros,
        'csrf' => csrf_campo(),
        'rota' => url('/instalar' . ($passo === 2 ? '/passo2' : '')),
        'conta_github' => (string) cfg('IN3_CONTA', 'LACibermedicina'),
        'rota_painel' => rota_painel(),
    ]), ['ativo' => 'sistema']);
    exit;
}

/* Uso por linha de comando: php public/index.php --instalar --usuario=... */
function instalador_cli(array $argv): void
{
    $op = [];
    foreach ($argv as $a) {
        if (preg_match('/^--([a-z0-9\-]+)(?:=(.*))?$/', $a, $m)) {
            $op[$m[1]] = $m[2] ?? '1';
        }
    }
    $rel = migrar();
    foreach ($rel as $r) {
        fwrite(STDOUT, "  ✓ {$r}\n");
    }
    configurar('host_publico', (string) cfg('IN3_HOST', 'in.m3d.pro'));
    if (existe_admin()) {
        fwrite(STDOUT, "  · administrador já existe; nada a fazer.\n");
        return;
    }
    $usuario = (string) ($op['usuario'] ?? '');
    $senha = (string) ($op['senha'] ?? '');
    if ($usuario === '' || $senha === '') {
        fwrite(STDOUT, "\n  Banco criado. Crie o administrador em " . rota_painel() . "/entrar\n"
            . "  ou rode: php public/index.php --instalar --usuario=SEU_USUARIO --senha=SUA_SENHA\n\n");
        return;
    }
    criar_usuario($usuario, $senha, 'admin', 4);
    fwrite(STDOUT, "  ✓ administrador '{$usuario}' criado.\n");
    fwrite(STDOUT, "    painel: " . rota_painel() . "/entrar\n");
}

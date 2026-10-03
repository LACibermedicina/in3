<?php
declare(strict_types=1);
/**
 * IN³ · verificador de privacidade e integridade.
 * Sai com código 1 se QUALQUER item interno ou camada bloqueada vazar para o
 * HTML público, para a API pública ou para o índice de busca anônimo.
 *
 * Uso: php tools/verificar.php [--base=http://localhost:8787]
 */

require_once dirname(__DIR__) . '/src/nucleo.php';

$base = '';
foreach ($argv as $a) {
    if (str_starts_with($a, '--base=')) {
        $base = rtrim(substr($a, 7), '/');
    }
}

$falhas = [];
$ok = [];

/* ---------------------------------------------- 1. itens internos no HTML */
$internos = consulta('SELECT slug, titulo, resumo, arquitetura, notas_internas, privacidade, riscos FROM projetos WHERE mostrar_ao_publico = 0');
$htmls = [];
foreach (glob(IN3_PUBLICO . '/*.html') ?: [] as $f) {
    $htmls[$f] = (string) file_get_contents($f);
}
if (!$htmls) {
    $ok[] = 'nenhum HTML estático em public/ (renderização em tempo de execução)';
}
foreach ($internos as $p) {
    foreach ($htmls as $arq => $html) {
        if ($p['titulo'] !== '' && mb_strpos($html, (string) $p['titulo']) !== false) {
            $falhas[] = 'título de item interno "' . $p['titulo'] . '" aparece em ' . basename($arq);
        }
    }
}
$ok[] = count($internos) . ' item(ns) interno(s) verificado(s) contra HTML estático';

/* ------------------------- 2. camadas bloqueadas não podem ir ao navegador */
foreach (consulta('SELECT * FROM projetos') as $p) {
    $nivel = nivel_valor((string) $p['nivel_divulgacao']);
    $anon = secoes_projeto($p, 1);          // simula visitante anônimo
    $profAnon = $anon['profundidade'];
    // a clareza anônima nunca pode passar da camada 1
    if ($profAnon > 1) {
        $falhas[] = 'projeto ' . $p['slug'] . ': profundidade anônima > 1 (' . $profAnon . ')';
    }
    // campos de camada 3/4 não podem estar "liberados" no anônimo
    foreach ($anon['secoes'] as $s) {
        if ($s['profundidade'] >= 3 && !empty($s['liberada'])) {
            $falhas[] = 'projeto ' . $p['slug'] . ': seção ' . $s['chave'] . ' liberada para anônimo';
        }
        if ($s['profundidade'] >= 3 && !empty($s['campos'])) {
            foreach ($s['campos'] as $v) {
                if (campo_publicavel((string) $v)) {
                    $falhas[] = 'projeto ' . $p['slug'] . ': conteúdo de camada ' . $s['profundidade'] . ' presente na montagem anônima';
                    break 2;
                }
            }
        }
    }
    if ($nivel >= 3 && campo_publicavel((string) $p['arquitetura'])) {
        // existe conteúdo técnico: garante que ele só sai com clareza >= 3
        $t3 = secoes_projeto($p, 3);
        $achou = false;
        foreach ($t3['secoes'] as $s) {
            if ($s['chave'] === 'tecnico' && !empty($s['liberada'])) {
                $achou = true;
            }
        }
        if (!$achou) {
            $falhas[] = 'projeto ' . $p['slug'] . ': camada técnica não liberada nem com clareza 3';
        }
    }
}
$ok[] = 'montagem de camadas conferida para ' . (int) escalar('SELECT count(*) FROM projetos') . ' projeto(s)';

/* ------------------------------------ 3. busca anônima não traz camada > 1 */
$termos = ['telemetria', 'LGPD', 'arquitetura', 'repositório', 'prontuário', 'riscos', 'privacidade'];
foreach ($termos as $t) {
    $r = busca($t, 1, false);
    foreach ($r['resultados'] as $res) {
        if ((int) $res['profundidade'] > 1) {
            $falhas[] = 'busca anônima por "' . $t . '" devolveu camada ' . (int) $res['profundidade'];
        }
        $proj = um('SELECT mostrar_ao_publico FROM projetos WHERE id = :i', [':i' => $res['id']]);
        if ($proj && (int) $proj['mostrar_ao_publico'] !== 1) {
            $falhas[] = 'busca anônima por "' . $t . '" devolveu item interno';
        }
    }
}
$ok[] = 'busca anônima conferida com ' . count($termos) . ' termo(s) sensível(is)';
$ok[] = 'motor de busca: ' . (fts_ligado() ? 'FTS5' : 'LIKE') . ' · indexado com filtro de escopo';

/* --------------------------------- 4. nenhuma credencial em arquivo do site */
$padroes = [
    '/password_hash\s*\(\s*[\'"][^\'"]+[\'"]/' => 'possível senha em hash literal no código',
    '/[\'"]senha[\'"]\s*=>\s*[\'"][^\'"]{6,}[\'"]/' => 'possível senha literal em array',
    '/ghp_[A-Za-z0-9]{20,}/' => 'token do GitHub em texto claro',
    '/ADMIN_PASSWORD\s*=\s*[\'"][^\'"]+[\'"]/' => 'senha de administrador embutida',
    '/BEGIN (RSA|OPENSSH) PRIVATE KEY/' => 'chave privada em arquivo do projeto',
];
$dirs = [IN3_PUBLICO, IN3_RAIZ . '/src', IN3_RAIZ . '/views', IN3_RAIZ . '/tools'];
$conferidos = 0;
foreach ($dirs as $d) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) {
            continue;
        }
        $ext = strtolower($f->getExtension());
        if (!in_array($ext, ['php', 'js', 'css', 'html', 'md', 'sh', 'json'], true)) {
            continue;
        }
        $txt = (string) file_get_contents((string) $f->getRealPath());
        $conferidos++;
        foreach ($padroes as $re => $msg) {
            if (preg_match($re, $txt)) {
                $falhas[] = $msg . ' → ' . $f->getFilename();
            }
        }
        // .env não pode existir dentro do que é servido
        if ($f->getFilename() === '.env') {
            $falhas[] = 'arquivo .env dentro de diretório servido: ' . $f->getPathname();
        }
    }
}
$ok[] = $conferidos . ' arquivo(s) do projeto varrido(s) por credenciais';

/* ------------------------------------ 5. usuários: hash é realmente hash */
foreach (consulta('SELECT id, usuario, senha_hash FROM usuarios') as $u) {
    $info = password_get_info((string) $u['senha_hash']);
    if (empty($info['algo'])) {
        $falhas[] = 'usuário ' . $u['usuario'] . ' sem hash reconhecido';
    }
    if (mb_strlen((string) $u['senha_hash']) < 20) {
        $falhas[] = 'usuário ' . $u['usuario'] . ' com hash suspeito (curto demais)';
    }
}
$ok[] = 'senhas conferidas como hash (' . (int) escalar('SELECT count(*) FROM usuarios') . ' usuário(s))';

/* --------------------------------- 6. rota do painel não é anunciada no site */
$rutas = [IN3_PUBLICO . '/robots.txt'];
foreach (glob(IN3_PUBLICO . '/*.html') ?: [] as $f) {
    $rutas[] = $f;
}
$acheiRota = false;
foreach ($rutas as $f) {
    if (is_file($f) && str_contains((string) file_get_contents($f), rota_painel())) {
        $acheiRota = true;
    }
}
if ($acheiRota) {
    $falhas[] = 'rota do painel (' . rota_painel() . ') aparece em arquivo público';
}
$ok[] = 'rota do painel (' . rota_painel() . ') ausente de robots.txt e do HTML público';

/* --------------------------------------- 7. API pública (se houver servidor) */
if ($base !== '') {
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
    $corpo = @file_get_contents($base . '/api/v1/publico/portfolio', false, $ctx);
    if ($corpo === false) {
        $ok[] = 'servidor em ' . $base . ' indisponível (etapa de API ignorada)';
    } else {
        $d = json_decode((string) $corpo, true);
        $idsInternos = array_map(static fn($x) => (string) $x['slug'], $internos);
        $achou = [];
        foreach (($d['projetos'] ?? []) as $pr) {
            if (in_array((string) $pr['slug'], $idsInternos, true)) {
                $achou[] = (string) $pr['slug'];
            }
            if ((int) ($pr['profundidade_liberada'] ?? 1) > 1) {
                $achou[] = (string) $pr['slug'] . ' (camada ' . (int) $pr['profundidade_liberada'] . ')';
            }
            foreach (['tecnico', 'playbook'] as $chave) {
                if (!empty($pr[$chave]) && $chave === 'playbook' && count(array_filter((array) $pr[$chave])) > 0) {
                    $achou[] = (string) $pr['slug'] . ' (playbook exposto)';
                }
            }
        }
        if ($achou) {
            $falhas[] = 'API pública expôs: ' . implode(', ', array_unique($achou));
        } else {
            $ok[] = 'API pública conferida: ' . count($d['projetos'] ?? []) . ' projeto(s), profundidade 1, nenhum item interno';
        }
    }
}

/* -------------------------------------------------------------- relatório */
fwrite(STDOUT, "\nIN³ · verificação de privacidade e integridade\n");
fwrite(STDOUT, str_repeat('─', 62) . "\n");
foreach ($ok as $o) {
    fwrite(STDOUT, "  ✓ {$o}\n");
}
if ($falhas) {
    fwrite(STDOUT, "\n");
    foreach ($falhas as $f) {
        fwrite(STDOUT, "  ✖ {$f}\n");
    }
    fwrite(STDOUT, "\n  " . count($falhas) . " FALHA(S) — não publique.\n\n");
    exit(1);
}
fwrite(STDOUT, "\n  Tudo certo: " . count($ok) . " verificação(ões) aprovada(s).\n\n");
exit(0);

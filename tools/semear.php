<?php
declare(strict_types=1);
/**
 * IN³ · semear o acervo — popula o banco a partir de:
 *   1. a curadoria legada (data/semear/portfolio.source.json + portfolio.full.json)
 *   2. o inventário GitHub já coletado (data/semear/github.*.cache.json)
 *
 * Regra de ouro: NADA é inventado. Campo sem fonte verificável fica vazio e,
 * portanto, não é publicado (o PLAYBOOK manda: "(pendente) não sai").
 *
 * Uso: php tools/semear.php [--sem-exemplo] [--wrapper-url=URL]
 */

require_once dirname(__DIR__) . '/src/nucleo.php';

$op = [];
foreach ($argv as $a) {
    if (preg_match('/^--([a-z0-9\-]+)(=?)(.*)$/', $a, $m)) {
        $op[$m[1]] = $m[3] !== '' ? $m[3] : '1';
    }
}
$dir = IN3_DADOS . '/semear';
$ler = static function (string $arq) {
    $p = IN3_DADOS . '/semear/' . $arq;
    if (!is_file($p)) {
        return null;
    }
    $d = json_decode((string) file_get_contents($p), true);
    return is_array($d) ? $d : null;
};

/* ---------------------------------------------------------------- esquema */
foreach (migrar() as $rel) {
    fwrite(STDOUT, "  · {$rel}\n");
}

/* ------------------------------------------------------------ repositórios */
$cacheRepos = $ler('github.repos.cache.json');
$cacheReadmes = $ler('github.readmes.cache.json');
$cacheCommits = $ler('github.commits.cache.json');

if ($cacheRepos === null) {
    fwrite(STDERR, "✖ data/semear/github.repos.cache.json ausente. Rode tools/sincronizar.php.\n");
    exit(1);
}

$achados = 0;
$visPublicos = 0;
transacao(static function () use ($cacheRepos, $cacheReadmes, $cacheCommits, &$achados, &$visPublicos): void {
    foreach ($cacheRepos as $r) {
        $nome = (string) ($r['name'] ?? '');
        if ($nome === '') {
            continue;
        }
        $vis = !empty($r['private']) ? 'privado' : 'publico';
        if ($vis === 'publico') {
            $visPublicos++;
        }
        $readme = is_array($cacheReadmes) ? ($cacheReadmes[$nome] ?? null) : null;
        $commits = is_array($cacheCommits) ? ($cacheCommits[$nome] ?? null) : null;
        $ult = is_array($commits) && $commits ? $commits[0] : null;
        $descricao = texto_limpo((string) ($r['description'] ?? ''), 300);
        // README do repositório é fonte real; guardamos o resumo para a camada 2/3.
        $resumoReadme = '';
        if (is_string($readme) && trim($readme) !== '') {
            $resumoReadme = preg_replace('/[#*`>\[\]_]/', ' ', $readme) ?? $readme;
            $resumoReadme = texto_limpo($resumoReadme, 900);
        }
        executa(
            "INSERT INTO repos (nome, nome_completo, url, descricao, linguagem, fork, visibilidade, topics,
                    tamanho_kb, gh_criado_em, gh_atualizado_em, gh_commit_sha, gh_commit_data, gh_commit_msg,
                    commit_total, sincronizado_em, resumo_readme)
             VALUES (:n, :nc, :u, :d, :lg, :fk, :vis, :tp, :tk, :gc, :ga, :cs, :cd, :cm, :ct, :q, :rr)
             ON CONFLICT(nome) DO UPDATE SET
                    nome_completo=:nc, url=:u, descricao=:d, linguagem=:lg, fork=:fk, visibilidade=:vis,
                    topics=:tp, tamanho_kb=:tk, gh_criado_em=:gc, gh_atualizado_em=:ga,
                    gh_commit_sha=:cs, gh_commit_data=:cd, gh_commit_msg=:cm, commit_total=:ct,
                    sincronizado_em=:q, resumo_readme=:rr",
            [
                ':n' => $nome,
                ':nc' => (string) ($r['full_name'] ?? $nome),
                ':u' => (string) ($r['html_url'] ?? ''),
                ':d' => $descricao,
                ':lg' => (string) ($r['language'] ?? ''),
                ':fk' => !empty($r['fork']) ? 1 : 0,
                ':vis' => $vis,
                ':tp' => json_encode($r['topics'] ?? [], JSON_UNESCAPED_UNICODE),
                ':tk' => (int) ($r['size'] ?? 0),
                ':gc' => (string) ($r['created_at'] ?? ''),
                ':ga' => (string) ($r['updated_at'] ?? ''),
                ':cs' => (string) ($ult['sha'] ?? ''),
                ':cd' => (string) ($ult['date'] ?? ''),
                ':cm' => texto_limpo((string) ($ult['msg'] ?? ''), 200),
                ':ct' => is_array($commits) ? count($commits) : 0,
                ':q' => agora(),
                ':rr' => $resumoReadme,
            ]
        );
        $achados++;
    }
    configurar('fonte_repos', 'inventário GitHub (API pública' . (is_file(IN3_RAIZ . '/.env') ? '' : '') . ')');
    configurar('sincronizado_em', agora());
});

fwrite(STDOUT, "  ✓ {$achados} repositório(s) inventariado(s) · {$visPublicos} público(s)\n");

/* ------------------------------------------------------------------- marca */
$fonte = $ler('portfolio.source.json');
if ($fonte) {
    $brand = (array) ($fonte['brand'] ?? []);
    configurar('slogan', (string) ($brand['slogan'] ?? 'O que entra na m3d, sai ao cubo.'));
    configurar('manifesto', (string) ($brand['subslogan'] ?? ''));
    configurar('host_publico', (string) ($brand['host_publico'] ?? 'in.m3d.pro'));
    $gh = (array) ($fonte['github'] ?? []);
    if (!empty($gh['conta'])) {
        configurar('conta_github', (string) $gh['conta']);
    }
}

/* ------------------------------------------------------------- projetos */
$full = $ler('portfolio.full.json') ?? [];
$fullPorId = [];
foreach (($full['projetos'] ?? []) as $fp) {
    $fullPorId[(string) ($fp['id'] ?? '')] = $fp;
}

/** Nível derivado do que EXISTE de conteúdo verificável (nunca chutado). */
function nivel_por_conteudo(array $campos): string
{
    if (campo_publicavel($campos['privacidade'] ?? '') || campo_publicavel($campos['riscos'] ?? '')) {
        return 'playbook_completo';
    }
    if (campo_publicavel($campos['arquitetura'] ?? '')) {
        return 'tecnico';
    }
    if (campo_publicavel($campos['problema'] ?? '') || campo_publicavel($campos['como_funciona'] ?? '')) {
        return 'institucional';
    }
    return 'institucional_roadmap';
}

$projetos = 0;
$mapaRepos = [];
if ($fonte && !empty($fonte['projetos'])) {
    foreach ($fonte['projetos'] as $p) {
        $slug = preg_replace('/[^a-z0-9\-]/', '', (string) ($p['id'] ?? ''));
        if ($slug === '') {
            continue;
        }
        $extra = $fullPorId[(string) ($p['id'] ?? '')] ?? [];
        $repos = array_values(array_filter(array_map('strval', (array) ($p['repos'] ?? []))));
        // resumo: camada pública (resumo_publico) e técnica (resumo_tecnico)
        $resumo = texto_limpo((string) ($extra['resumo_publico'] ?? ''), 600);
        if ($resumo === '') {
            $resumo = texto_limpo((string) ($extra['resumo'] ?? ''), 600);
        }
        $resumoTec = texto_limpo((string) ($extra['resumo_tecnico'] ?? ''), 900);
        $tech = array_values(array_filter(array_map('strval', (array) ($p['tech_extra'] ?? []))));
        foreach ((array) ($extra['tech'] ?? []) as $t) {
            $tech[] = (string) $t;
        }
        $marcos = (array) ($p['linha_do_tempo'] ?? $extra['linha_do_tempo'] ?? []);

        // "como funciona" em linguagem humana: extraído do README real do repositório principal
        $comoFunciona = '';
        foreach ($repos as $rn) {
            $row = um('SELECT resumo_readme, descricao FROM repos WHERE nome = :n', [':n' => $rn]);
            if ($row && $row['resumo_readme'] !== '') {
                $comoFunciona = texto_limpo((string) $row['resumo_readme'], 700);
                break;
            }
        }
        $problema = [];
        $solucao = [];
        foreach ($repos as $rn) {
            $row = um('SELECT descricao FROM repos WHERE nome = :n', [':n' => $rn]);
            if ($row && trim((string) $row['descricao']) !== '') {
                $problema[] = (string) $row['descricao'];
            }
        }
        $problemaTxt = $problema ? texto_limpo('Extraído das descrições oficiais dos repositórios: ' . implode(' · ', array_slice($problema, 0, 3)), 600) : '';

        $campos = [
            'resumo' => $resumo,
            'como_funciona' => $comoFunciona,
            'problema' => $problemaTxt,
            'solucao' => $problemaTxt,
            'arquitetura' => $resumoTec,
            'privacidade' => '',
            'riscos' => '',
        ];
        $nivel = nivel_por_conteudo($campos);

        executa(
            "INSERT INTO projetos (slug, titulo, emoji, icone, categoria, status, ordem,
                    mostrar_ao_publico, mostrar_link_repo, nivel_divulgacao, resumo, roadmap,
                    publico_alvo, problema, solucao, como_funciona, validacao, arquitetura, integracoes,
                    privacidade, riscos, notas_internas, playbook_ref, atualizado_em)
             VALUES (:slug, :titulo, :emoji, :icone, :cat, :st, :ord, :pub, :link, :nivel, :resumo, '[]',
                    '', :prob, :sol, :cf, '', :arq, '', '', '', '', :pb, :q)
             ON CONFLICT(slug) DO UPDATE SET
                    titulo=:titulo, emoji=:emoji, icone=:icone, categoria=:cat, status=:st,
                    mostrar_ao_publico=:pub, nivel_divulgacao=:nivel, resumo=:resumo,
                    problema=:prob, solucao=:sol, como_funciona=:cf, arquitetura=:arq,
                    playbook_ref=:pb, atualizado_em=:q",
            [
                ':slug' => $slug,
                ':titulo' => texto_limpo((string) ($p['titulo'] ?? $slug), 160),
                ':emoji' => mb_substr((string) ($p['emoji'] ?? '🧊'), 0, 8),
                ':icone' => preg_replace('/[^a-z_]/', '', (string) ($extra['imagem_svg'] ?? $p['imagem_svg'] ?? 'cubo')) ?: 'cubo',
                ':cat' => texto_limpo((string) ($p['categoria'] ?? ''), 80),
                ':st' => texto_limpo((string) ($p['status'] ?? ''), 40),
                ':ord' => (int) (array_search($p, $fonte['projetos'], true) ?: 100) * 10,
                ':pub' => !empty($p['mostrar_ao_publico']) ? 1 : 0,
                ':link' => !empty($p['mostrar_link_repo']) ? 1 : 0,
                ':nivel' => $nivel,
                ':resumo' => $resumo,
                ':prob' => $problemaTxt,
                ':sol' => $problemaTxt,
                ':cf' => $comoFunciona,
                ':arq' => $resumoTec,
                ':pb' => texto_limpo((string) ($p['playbook'] ?? $extra['playbook'] ?? ''), 160),
                ':q' => agora(),
            ]
        );
        $id = (int) (um('SELECT id FROM projetos WHERE slug = :s', [':s' => $slug])['id'] ?? 0);
        if ($id === 0) {
            continue;
        }
        definir_tecnologias($id, implode(', ', array_slice(array_unique($tech), 0, 24)));
        definir_repos($id, $repos);
        $linhas = [];
        foreach ($marcos as $m) {
            $linhas[] = (string) ($m['data'] ?? '') . ' | ' . (string) ($m['marco'] ?? '');
        }
        definir_marcos($id, implode("\n", $linhas));
        indexar_projeto($id);
        $projetos++;
        foreach ($repos as $rn) {
            $mapaRepos[$rn] = true;
        }
    }
}

/* ------------------------------------- repositórios ainda sem projeto ---- */
$orfaos = consulta(
    'SELECT r.* FROM repos r WHERE r.id NOT IN (SELECT repo_id FROM projeto_repos) ORDER BY r.gh_atualizado_em DESC'
);
if ($orfaos) {
    fwrite(STDOUT, "\n  ! " . count($orfaos) . " repositório(s) sem projeto — criando itens de curadoria:\n");
    foreach ($orfaos as $r) {
        $nome = (string) $r['nome'];
        fwrite(STDOUT, "      · {$nome}\n");
        // Todo repositório órfão entra como item INTERNO (nasce não publicado),
        // exatamente como manda a política: só o administrador publica.
        $slug = slugificar($nome);
        // Dois repositórios podem normalizar para o mesmo slug (ex.: "Contos-Contas"
        // e "Contos_Contas"). Sem isto, um deles ficaria sem vínculo.
        $base = $slug;
        $sufixo = 2;
        while (true) {
            $ex = um('SELECT id FROM projetos WHERE slug = :s', [':s' => $slug]);
            if (!$ex) {
                break;
            }
            $mapeia = (int) escalar(
                'SELECT count(*) FROM projeto_repos pr JOIN repos r ON r.id = pr.repo_id
                  WHERE pr.projeto_id = :p AND r.nome = :n',
                [':p' => $ex['id'], ':n' => $nome]
            );
            if ($mapeia > 0 || $ex['id'] === 0) {
                break;
            }
            $slug = $base . '-' . $sufixo++;
        }
        $categoria = 'Governança & Infra';
        $icone = 'governanca';
        if (stripos($nome, 'conto') !== false || stripos($nome, 'fiscal') !== false) {
            $categoria = 'Fiscal & Finanças';
            $icone = 'fiscal';
        }
        executa(
            "INSERT INTO projetos (slug, titulo, emoji, icone, categoria, status, ordem, mostrar_ao_publico,
                    nivel_divulgacao, resumo, problema, solucao, arquitetura, atualizado_em)
             VALUES (:slug, :titulo, '🧊', :icone, :cat, 'backlog', 900, 0, 'institucional_roadmap',
                    :res, :res, :res, :arq, :q)
             ON CONFLICT(slug) DO UPDATE SET resumo=:res, atualizado_em=:q",
            [
                ':slug' => $slug,
                ':titulo' => $nome,
                ':icone' => $icone,
                ':cat' => $categoria,
                ':res' => texto_limpo('Repositório do acervo ainda sem ficha de curadoria: ' . ($r['descricao'] !== '' ? (string) $r['descricao'] : 'sem descrição no GitHub') . '.', 600),
                ':arq' => texto_limpo((string) $r['resumo_readme'], 700),
                ':q' => agora(),
            ]
        );
        $id = (int) (um('SELECT id FROM projetos WHERE slug = :s', [':s' => $slug])['id'] ?? 0);
        if ($id > 0) {
            definir_repos($id, [$nome]);
            definir_tecnologias($id, implode(', ', array_filter([(string) $r['linguagem']])));
            indexar_projeto($id);
            $projetos++;
        }
    }
}

/* ------------------------------------------- conta do acervo + exemplos --- */
$conta = (string) configuracao('conta_github', (string) cfg('IN3_CONTA', 'LACibermedicina'));
if ($conta !== '') {
    configurar('conta_github', $conta);
}

/* --sem-exemplo: nenhum dado de formulário de teste é criado ------------- */
if (!isset($op['sem-exemplo'])) {
    $n = (int) escalar('SELECT count(*) FROM pedidos');
    if ($n === 0) {
        fwrite(STDOUT, "  · nenhum pedido de exemplo criado (use o formulário público para testar).\n");
    }
}

$cob = cobertura_repos();
fwrite(STDOUT, "\n  ✓ {$projetos} projeto(s) no acervo\n");
fwrite(STDOUT, '  ✓ cobertura de repositórios: ' . $cob['mapeados'] . '/' . $cob['total']
    . ($cob['completo'] ? ' (completa)' : ' — ' . count($cob['orfaos']) . ' pendente(s)') . "\n");
fwrite(STDOUT, '  ✓ busca: ' . (fts_ligado() ? 'FTS5' : 'LIKE') . ' · ' . (int) escalar('SELECT count(*) FROM busca_texto') . " linha(s)\n");

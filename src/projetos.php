<?php
declare(strict_types=1);
/**
 * IN³ · curadoria, níveis de divulgação e montagem do hotsite.
 *
 * Contrato de segurança (o coração do sistema):
 *   profundidade liberada = min(nível do projeto, clareza do visitante)
 * Um projeto com nível 2 nunca publica conteúdo de camada 3, mesmo para
 * um visitante com clareza 3 — o nível do projeto é o teto.
 */

function nivel_valor(?string $nivel): int
{
    return IN3_NIVEIS[$nivel ?? ''] ?? 1;
}

function nivel_rotulo(int $n): string
{
    return IN3_NIVEL_ROTULO[$n] ?? ('Camada ' . $n);
}

/** Código textual correspondente a uma clareza numérica (1..4). */
function nivel_chave(int $n): string
{
    foreach (IN3_NIVEIS as $chave => $valor) {
        if ($valor === $n) {
            return (string) $chave;
        }
    }
    return 'institucional_roadmap';
}

/* -------------------------------------------------------------- consultas */

function projeto_por_slug(string $slug): ?array
{
    return um('SELECT * FROM projetos WHERE slug = :s', [':s' => $slug]);
}

function projeto_por_id(int $id): ?array
{
    return um('SELECT * FROM projetos WHERE id = :i', [':i' => $id]);
}

function projetos_visiveis(int $clareza, bool $admin = false, array $filtro = []): array
{
    $sql = 'SELECT p.* FROM projetos p WHERE 1=1';
    $par = [];
    if (!$admin) {
        $sql .= ' AND p.mostrar_ao_publico = 1';
    }
    if (!empty($filtro['categoria'])) {
        $sql .= ' AND p.categoria = :cat';
        $par[':cat'] = $filtro['categoria'];
    }
    if (!empty($filtro['status'])) {
        $sql .= ' AND p.status = :st';
        $par[':st'] = $filtro['status'];
    }
    if (!empty($filtro['nivel'])) {
        $sql .= ' AND p.nivel_divulgacao = :nv';
        $par[':nv'] = $filtro['nivel'];
    }
    $sql .= ' ORDER BY p.ordem ASC, p.titulo ASC';
    $linhas = consulta($sql, $par);
    foreach ($linhas as &$l) {
        $l['_profundidade'] = min(nivel_valor($l['nivel_divulgacao']), $clareza);
        $l['_repos'] = projeto_repos((int) $l['id']);
        $l['_tech'] = projeto_tech((int) $l['id']);
        $l['_marcos'] = projeto_marcos((int) $l['id']);
    }
    return $linhas;
}

function projeto_repos(int $projetoId): array
{
    return consulta(
        'SELECT r.* FROM projeto_repos pr JOIN repos r ON r.id = pr.repo_id
          WHERE pr.projeto_id = :p ORDER BY r.nome ASC',
        [':p' => $projetoId]
    );
}

function projeto_tech(int $projetoId): array
{
    $t = consulta('SELECT tech FROM projeto_tech WHERE projeto_id = :p ORDER BY tech', [':p' => $projetoId]);
    return array_map(static fn($x) => (string) $x['tech'], $t);
}

function projeto_marcos(int $projetoId): array
{
    return consulta(
        'SELECT data, marco FROM projeto_marcos WHERE projeto_id = :p ORDER BY ordem ASC, data ASC',
        [':p' => $projetoId]
    );
}

function categorias_publicas(bool $admin = false): array
{
    $sql = 'SELECT categoria, count(*) AS n FROM projetos WHERE 1=1';
    if (!$admin) {
        $sql .= ' AND mostrar_ao_publico = 1';
    }
    return consulta($sql . ' GROUP BY categoria ORDER BY n DESC, categoria ASC');
}

function status_disponiveis(bool $admin = false): array
{
    $sql = 'SELECT status, count(*) AS n FROM projetos WHERE 1=1';
    if (!$admin) {
        $sql .= ' AND mostrar_ao_publico = 1';
    }
    return consulta($sql . ' GROUP BY status ORDER BY n DESC, status ASC');
}

/* ------------------------------------------- cobertura de repositórios */

/** Todo repo do inventário mapeado? Usado no painel e no verificador. */
function cobertura_repos(): array
{
    $total = (int) escalar('SELECT count(*) FROM repos');
    $mapeados = (int) escalar('SELECT count(DISTINCT repo_id) FROM projeto_repos');
    $semProjeto = consulta(
        'SELECT r.* FROM repos r
          WHERE r.id NOT IN (SELECT repo_id FROM projeto_repos)
          ORDER BY r.nome'
    );
    $porVisibilidade = consulta('SELECT visibilidade, count(*) AS n FROM repos GROUP BY visibilidade');
    return [
        'total'       => $total,
        'mapeados'    => $mapeados,
        'orfaos'      => $semProjeto,
        'visibilidade' => $porVisibilidade,
        'completo'    => $total > 0 && count($semProjeto) === 0,
    ];
}

/* --------------------------------------------------- hotsite do projeto */

/**
 * Seções do hotsite, cada uma com a profundidade mínima exigida.
 * A montagem decide o que entra; nada é escondido só no HTML.
 */
function secoes_projeto(array $p, int $clareza): array
{
    $nivelProjeto = nivel_valor($p['nivel_divulgacao']);
    $prof = min($nivelProjeto, $clareza);
    $admin = eh_admin();

    $repos = projeto_repos((int) $p['id']);
    $tech  = projeto_tech((int) $p['id']);
    $marcos = projeto_marcos((int) $p['id']);

    $secoes = [];

    $secoes[] = [
        'chave' => 'identidade', 'titulo' => 'O projeto', 'profundidade' => 1,
        'liberada' => true, 'obrigatoria' => true,
        'campos' => [
            'Categoria' => (string) $p['categoria'],
            'Situação'  => (string) $p['status'],
            'Nível de divulgação' => nivel_rotulo($nivelProjeto),
        ],
        'texto' => (string) $p['resumo'],
    ];

    $secoes[] = [
        'chave' => 'roadmap', 'titulo' => 'Roadmap institucional', 'profundidade' => 1,
        'linhas' => array_map(static fn($m) => [
            'data' => data_br((string) $m['data']),
            'marco' => (string) $m['marco'],
        ], $marcos),
        'liberada' => true,
    ];

    $secoes[] = [
        'chave' => 'institucional', 'titulo' => 'Leitura institucional', 'profundidade' => 2,
        'liberada' => $prof >= 2,
        'campos' => [
            'Público-alvo'          => (string) $p['publico_alvo'],
            'Problema'              => (string) $p['problema'],
            'Solução'               => (string) $p['solucao'],
            'Como funciona (linguagem humana)' => (string) $p['como_funciona'],
            'Validação'             => (string) $p['validacao'],
        ],
    ];

    $secoes[] = [
        'chave' => 'tecnico', 'titulo' => 'Detalhamento técnico', 'profundidade' => 3,
        'liberada' => $prof >= 3,
        'campos' => [
            'Arquitetura' => (string) $p['arquitetura'],
            'Integrações' => (string) $p['integracoes'],
        ],
        'tecnologias' => $tech,
        'repositorios' => (((int) $p['mostrar_link_repo'] === 1) || $admin) ? $repos : [],
        'repos_contagem' => count($repos),
        'exibe_repos' => ((int) $p['mostrar_link_repo'] === 1),
    ];

    $secoes[] = [
        'chave' => 'playbook', 'titulo' => 'Playbook completo', 'profundidade' => 4,
        'liberada' => $prof >= 4,
        'campos' => [
            'Privacidade e LGPD' => (string) $p['privacidade'],
            'Riscos conhecidos'  => (string) $p['riscos'],
            'Referência no playbook' => (string) $p['playbook_ref'],
        ],
    ];

    if ($admin) {
        $secoes[] = [
            'chave' => 'interno', 'titulo' => 'Notas internas (só administração)', 'profundidade' => 4,
            'liberada' => true, 'interno' => true,
            'campos' => ['Notas' => (string) $p['notas_internas']],
        ];
    }

    // Camada bloqueada NÃO carrega conteúdo: ele não sai do servidor.
    // Sem isto, o texto ficaria disponível para qualquer consumidor da
    // estrutura (mesmo sem ir ao HTML) — o que violaria a política.
    foreach ($secoes as &$s) {
        if (empty($s['liberada'])) {
            unset($s['campos'], $s['texto'], $s['linhas'], $s['tecnologias'], $s['repositorios']);
        }
    }
    unset($s);

    return ['profundidade' => $prof, 'nivel_projeto' => $nivelProjeto, 'secoes' => $secoes];
}

/** Próximo nível que o visitante ainda não pode ler (para o CTA de pedido). */
function proximo_nivel_bloqueado(int $nivelProjeto, int $clareza): ?int
{
    $prof = min($nivelProjeto, $clareza);
    return $prof < $nivelProjeto ? $prof + 1 : null;
}

/* ---------------------------------------------------- pedidos de detalhe */

function registrar_pedido(array $d): array
{
    $nome = texto_limpo((string) ($d['nome'] ?? ''), 120);
    $contato = texto_limpo((string) ($d['contato'] ?? ''), 160);
    $projeto = texto_limpo((string) ($d['projeto'] ?? ''), 120);
    $nivel = in_array(($d['nivel'] ?? ''), ['institucional', 'tecnico', 'playbook'], true) ? (string) $d['nivel'] : 'institucional';
    $mensagem = texto_limpo((string) ($d['mensagem'] ?? ''), 1200);

    $erros = [];
    if (mb_strlen($nome) < 3) {
        $erros[] = 'Informe seu nome.';
    }
    if (!filter_var($contato, FILTER_VALIDATE_EMAIL) && !preg_match('/^\+?[0-9 ()\-]{8,20}$/', $contato)) {
        $erros[] = 'Informe um e-mail ou telefone válido.';
    }
    if (mb_strlen($mensagem) < 10) {
        $erros[] = 'Descreva brevemente o que você precisa (mínimo 10 caracteres).';
    }
    // freio simples contra abuso: 5 pedidos por hora por IP
    $recentes = (int) escalar(
        'SELECT count(*) FROM pedidos WHERE ip = :ip AND quando > :d',
        [':ip' => ip_visitante(), ':d' => gmdate('Y-m-d\TH:i:s\Z', time() - 3600)]
    );
    if ($recentes >= (int) cfg('IN3_LIMITE_PEDIDOS', '5')) {
        $erros[] = 'Você já enviou vários pedidos na última hora. Aguarde nossa resposta.';
    }
    if ($erros) {
        return ['ok' => false, 'erros' => $erros];
    }

    executa(
        'INSERT INTO pedidos (quando, nome, contato, projeto, nivel, mensagem, ip, estado)
         VALUES (:q, :n, :c, :p, :nv, :m, :ip, :e)',
        [
            ':q' => agora(), ':n' => $nome, ':c' => $contato, ':p' => $projeto,
            ':nv' => $nivel, ':m' => $mensagem, ':ip' => ip_visitante(), ':e' => 'novo',
        ]
    );
    $id = ultimo_id();
    auditar('pedido_criado', 'pedidos', $id, $projeto . ' / ' . $nivel);
    return ['ok' => true, 'id' => $id];
}

function pedidos(?string $estado = null, int $limite = 200): array
{
    if ($estado) {
        return consulta('SELECT * FROM pedidos WHERE estado = :e ORDER BY quando DESC LIMIT ' . (int) $limite, [':e' => $estado]);
    }
    return consulta('SELECT * FROM pedidos ORDER BY quando DESC LIMIT ' . (int) $limite);
}

/* --------------------------------------------------------- escrita painel */

function salvar_projeto(array $d): int
{
    $id = (int) ($d['id'] ?? 0);
    $campos = [
        'titulo' => texto_limpo((string) ($d['titulo'] ?? ''), 160),
        'emoji' => mb_substr((string) ($d['emoji'] ?? '🧊'), 0, 8),
        'icone' => preg_replace('/[^a-z_]/', '', (string) ($d['icone'] ?? 'cubo')) ?: 'cubo',
        'categoria' => texto_limpo((string) ($d['categoria'] ?? ''), 80),
        'status' => texto_limpo((string) ($d['status'] ?? ''), 40),
        'resumo' => texto_limpo((string) ($d['resumo'] ?? ''), 600),
        'publico_alvo' => texto_limpo((string) ($d['publico_alvo'] ?? ''), 400),
        'problema' => texto_limpo((string) ($d['problema'] ?? ''), 600),
        'solucao' => texto_limpo((string) ($d['solucao'] ?? ''), 600),
        'como_funciona' => texto_limpo((string) ($d['como_funciona'] ?? ''), 700),
        'validacao' => texto_limpo((string) ($d['validacao'] ?? ''), 500),
        'arquitetura' => texto_limpo((string) ($d['arquitetura'] ?? ''), 900),
        'integracoes' => texto_limpo((string) ($d['integracoes'] ?? ''), 600),
        'privacidade' => texto_limpo((string) ($d['privacidade'] ?? ''), 800),
        'riscos' => texto_limpo((string) ($d['riscos'] ?? ''), 800),
        'notas_internas' => texto_limpo((string) ($d['notas_internas'] ?? ''), 1200),
        'playbook_ref' => texto_limpo((string) ($d['playbook_ref'] ?? ''), 160),
    ];
    $nivel = array_key_exists((string) ($d['nivel_divulgacao'] ?? ''), IN3_NIVEIS) ? (string) $d['nivel_divulgacao'] : 'institucional_roadmap';
    $mostrar = !empty($d['mostrar_ao_publico']) ? 1 : 0;
    $link = !empty($d['mostrar_link_repo']) ? 1 : 0;

    if ($campos['titulo'] === '') {
        throw new InvalidArgumentException('O título é obrigatório.');
    }
    $ordem = (int) ($d['ordem'] ?? 100);

    if ($id > 0) {
        $campos['id'] = $id;
        $campos['nivel'] = $nivel;
        $campos['pub'] = $mostrar;
        $campos['link'] = $link;
        $campos['ordem'] = $ordem;
        executa(
            'UPDATE projetos SET titulo=:titulo, emoji=:emoji, icone=:icone, categoria=:categoria,
                    status=:status, resumo=:resumo, publico_alvo=:publico_alvo, problema=:problema,
                    solucao=:solucao, como_funciona=:como_funciona, validacao=:validacao,
                    arquitetura=:arquitetura, integracoes=:integracoes, privacidade=:privacidade,
                    riscos=:riscos, notas_internas=:notas_internas, playbook_ref=:playbook_ref,
                    nivel_divulgacao=:nivel, mostrar_ao_publico=:pub, mostrar_link_repo=:link,
                    ordem=:ordem, atualizado_em=:q
             WHERE id=:id',
            $campos + [':q' => agora()]
        );
        auditar('projeto_atualizado', 'projetos', $id, 'nivel=' . $nivel . ' publico=' . $mostrar);
    } else {
        $slug = slugificar((string) ($d['slug'] ?? $campos['titulo']));
        if ($slug === '') {
            throw new InvalidArgumentException('Não foi possível gerar um slug a partir do título.');
        }
        executa(
            'INSERT INTO projetos (slug, titulo, emoji, icone, categoria, status, resumo, publico_alvo,
                    problema, solucao, como_funciona, validacao, arquitetura, integracoes, privacidade,
                    riscos, notas_internas, playbook_ref, nivel_divulgacao, mostrar_ao_publico,
                    mostrar_link_repo, ordem, atualizado_em)
             VALUES (:slug, :titulo, :emoji, :icone, :categoria, :status, :resumo, :publico_alvo,
                    :problema, :solucao, :como_funciona, :validacao, :arquitetura, :integracoes,
                    :privacidade, :riscos, :notas_internas, :playbook_ref, :nivel, :pub, :link, :ordem, :q)',
            [
                ':slug' => $slug, ':titulo' => $campos['titulo'], ':emoji' => $campos['emoji'],
                ':icone' => $campos['icone'], ':categoria' => $campos['categoria'], ':status' => $campos['status'],
                ':resumo' => $campos['resumo'], ':publico_alvo' => $campos['publico_alvo'],
                ':problema' => $campos['problema'], ':solucao' => $campos['solucao'],
                ':como_funciona' => $campos['como_funciona'], ':validacao' => $campos['validacao'],
                ':arquitetura' => $campos['arquitetura'], ':integracoes' => $campos['integracoes'],
                ':privacidade' => $campos['privacidade'], ':riscos' => $campos['riscos'],
                ':notas_internas' => $campos['notas_internas'], ':playbook_ref' => $campos['playbook_ref'],
                ':nivel' => $nivel, ':pub' => $mostrar, ':link' => $link, ':ordem' => $ordem, ':q' => agora(),
            ]
        );
        $id = ultimo_id();
        auditar('projeto_criado', 'projetos', $id, 'nivel=' . $nivel . ' publico=' . $mostrar);
    }

    definir_tecnologias($id, (string) ($d['tecnologias'] ?? ''));
    indexar_projeto($id);
    return $id;
}

function definir_tecnologias(int $projetoId, string $csv): void
{
    executa('DELETE FROM projeto_tech WHERE projeto_id = :p', [':p' => $projetoId]);
    $itens = array_filter(array_map('trim', explode(',', $csv)));
    foreach (array_slice($itens, 0, 30) as $t) {
        if ($t !== '') {
            executa('INSERT OR IGNORE INTO projeto_tech (projeto_id, tech) VALUES (:p, :t)', [':p' => $projetoId, ':t' => texto_limpo($t, 60)]);
        }
    }
}

function definir_repos(int $projetoId, array $nomes): void
{
    executa('DELETE FROM projeto_repos WHERE projeto_id = :p', [':p' => $projetoId]);
    foreach ($nomes as $n) {
        $r = um('SELECT id FROM repos WHERE nome = :n', [':n' => (string) $n]);
        if ($r) {
            executa('INSERT OR IGNORE INTO projeto_repos (projeto_id, repo_id) VALUES (:p, :r)', [':p' => $projetoId, ':r' => $r['id']]);
        }
    }
}

function definir_marcos(int $projetoId, string $texto): void
{
    executa('DELETE FROM projeto_marcos WHERE projeto_id = :p', [':p' => $projetoId]);
    $ordem = 0;
    foreach (preg_split('/\r?\n/', $texto) ?: [] as $linha) {
        $linha = trim($linha);
        if ($linha === '' || !str_contains($linha, '|')) {
            continue;
        }
        [$data, $marco] = array_map('trim', explode('|', $linha, 2));
        executa(
            'INSERT INTO projeto_marcos (projeto_id, data, marco, ordem) VALUES (:p, :d, :m, :o)',
            [':p' => $projetoId, ':d' => $data, ':m' => texto_limpo($marco, 220), ':o' => $ordem++]
        );
    }
}

/* ------------------------------------------------------------- indexação */

/**
 * Reescreve a busca do projeto em camadas cumulativas.
 * Escopo 1 contém APENAS texto publicável na camada 1 — é isso que garante
 * que uma busca pública jamais devolva trecho interno.
 */
function indexar_projeto(int $projetoId): void
{
    $p = projeto_por_id($projetoId);
    if (!$p) {
        return;
    }
    $marcos = projeto_marcos($projetoId);
    $tech = projeto_tech($projetoId);
    $repos = projeto_repos($projetoId);

    $c1 = implode(' ', array_filter([
        (string) $p['titulo'], (string) $p['categoria'], (string) $p['status'],
        campo_publicavel((string) $p['resumo']) ? (string) $p['resumo'] : '',
        implode(' ', array_map(static fn($m) => campo_publicavel((string) $m['marco']) ? (string) $m['marco'] : '', $marcos)),
    ]));

    $c2 = implode(' ', array_filter([
        $c1,
        (string) $p['publico_alvo'], (string) $p['problema'], (string) $p['solucao'],
        (string) $p['como_funciona'], (string) $p['validacao'],
    ]));

    $c3 = implode(' ', array_filter([
        $c2, (string) $p['arquitetura'], (string) $p['integracoes'],
        implode(' ', $tech), implode(' ', array_map(static fn($r) => (string) $r['nome'], $repos)),
    ]));

    $c4 = implode(' ', array_filter([
        $c3, (string) $p['privacidade'], (string) $p['riscos'],
        (string) $p['playbook_ref'], (string) $p['notas_internas'],
    ]));

    transacao(static function () use ($projetoId, $p, $c1, $c2, $c3, $c4): void {
        executa('DELETE FROM busca_texto WHERE projeto_id = :p', [':p' => $projetoId]);
        if (fts_ligado()) {
            executa('DELETE FROM busca_fts WHERE projeto = :p', [':p' => $projetoId]);
        }
        foreach ([1 => $c1, 2 => $c2, 3 => $c3, 4 => $c4] as $escopo => $corpo) {
            executa(
                'INSERT INTO busca_texto (projeto_id, escopo, slug, titulo, categoria, corpo)
                 VALUES (:p, :e, :s, :t, :c, :corpo)',
                [
                    ':p' => $projetoId, ':e' => $escopo, ':s' => (string) $p['slug'],
                    ':t' => (string) $p['titulo'], ':c' => (string) $p['categoria'], ':corpo' => $corpo,
                ]
            );
            if (fts_ligado()) {
                executa(
                    'INSERT INTO busca_fts (projeto, escopo, slug, titulo, categoria, corpo)
                     VALUES (:p, :e, :s, :t, :c, :corpo)',
                    [
                        ':p' => $projetoId, ':e' => $escopo, ':s' => (string) $p['slug'],
                        ':t' => (string) $p['titulo'], ':c' => (string) $p['categoria'], ':corpo' => $corpo,
                    ]
                );
            }
        }
    });
}

function reindexar_tudo(): int
{
    $ids = consulta('SELECT id FROM projetos');
    foreach ($ids as $r) {
        indexar_projeto((int) $r['id']);
    }
    return count($ids);
}

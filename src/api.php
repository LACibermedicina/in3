<?php
declare(strict_types=1);
/**
 * IN³ · API JSON (v1).
 * O mesmo filtro do HTML é aplicado aqui: nenhuma rota pública devolve item
 * interno nem camada acima da clareza do solicitante.
 */

/** Projeção pública de um projeto — só o que a profundidade libera. */
function api_projeto(array $p, int $clareza): array
{
    $m = secoes_projeto($p, $clareza);
    $prof = $m['profundidade'];
    $base = [
        'slug' => (string) $p['slug'],
        'titulo' => (string) $p['titulo'],
        'emoji' => (string) $p['emoji'],
        'icone' => (string) $p['icone'],
        'categoria' => (string) $p['categoria'],
        'status' => (string) $p['status'],
        'publico' => (int) $p['mostrar_ao_publico'] === 1,
        'nivel_projeto' => nivel_rotulo(nivel_valor((string) $p['nivel_divulgacao'])),
        'nivel_projeto_codigo' => nivel_valor((string) $p['nivel_divulgacao']),
        'profundidade_liberada' => $prof,
        'roadmap' => array_map(static fn($x) => ['data' => (string) $x['data'], 'marco' => (string) $x['marco']], $p['_marcos'] ?? []),
        'voxel' => voxel_projeto($p, true),
    ];
    if (campo_publicavel((string) $p['resumo'])) {
        $base['resumo'] = (string) $p['resumo'];
    }
    if ($prof >= 2) {
        $base['institucional'] = array_filter([
            'publico_alvo' => (string) $p['publico_alvo'],
            'problema' => (string) $p['problema'],
            'solucao' => (string) $p['solucao'],
            'como_funciona' => (string) $p['como_funciona'],
            'validacao' => (string) $p['validacao'],
        ], static fn($v) => campo_publicavel((string) $v));
    }
    if ($prof >= 3) {
        $base['tecnico'] = [
            'arquitetura' => (string) $p['arquitetura'],
            'integracoes' => (string) $p['integracoes'],
            'tecnologias' => $p['_tech'] ?? [],
            'repositorios' => ((int) $p['mostrar_link_repo'] === 1 || eh_admin())
                ? array_map(static fn($r) => ['nome' => (string) $r['nome'], 'url' => (string) $r['url'], 'linguagem' => (string) $r['linguagem']], $p['_repos'] ?? [])
                : [],
        ];
    }
    if ($prof >= 4) {
        $base['playbook'] = array_filter([
            'privacidade' => (string) $p['privacidade'],
            'riscos' => (string) $p['riscos'],
            'referencia' => (string) $p['playbook_ref'],
        ], static fn($v) => campo_publicavel((string) $v));
    }
    return $base;
}

function api_portfolio(int $clareza, bool $admin = false, array $filtro = []): array
{
    $ps = projetos_visiveis($clareza, $admin, $filtro);
    $itens = [];
    foreach ($ps as $p) {
        $itens[] = api_projeto($p, $clareza);
    }
    $vis = [];
    foreach (cobertura_repos()['visibilidade'] as $v) {
        $vis[(string) $v['visibilidade']] = (int) $v['n'];
    }
    return [
        'api' => 'in3/v1',
        'gerado_em' => agora(),
        'politica' => 'Somente itens liberados; a profundidade é limitada pelo menor valor entre o nível do projeto e a clareza do solicitante.',
        'fonte' => [
            'repositorios' => (string) configuracao('fonte_repos', 'não sincronizado'),
            'curadoria' => (string) configuracao('fonte_curadoria', 'não semeado'),
            'sincronizado_em' => (string) configuracao('sincronizado_em', ''),
        ],
        'contagens' => [
            'projetos_visiveis' => count($itens),
            'projetos_total' => (int) escalar('SELECT count(*) FROM projetos'),
            'repos_inventariados' => $vis,
        ],
        'filtros_aplicados' => array_filter($filtro),
        'projetos' => $itens,
    ];
}

function api_tecnologias(int $clareza, bool $admin = false): array
{
    $sql = 'SELECT t.tech, count(DISTINCT p.id) AS n
              FROM projeto_tech t JOIN projetos p ON p.id = t.projeto_id WHERE 1=1';
    $par = [];
    if (!$admin) {
        $sql .= ' AND p.mostrar_ao_publico = 1';
    }
    $sql .= ' GROUP BY t.tech ORDER BY n DESC, t.tech ASC';
    return ['api' => 'in3/v1', 'gerado_em' => agora(), 'tecnologias' => consulta($sql, $par)];
}

function api_meta(bool $admin = false): array
{
    $cob = cobertura_repos();
    return [
        'api' => 'in3/v1',
        'gerado_em' => agora(),
        'marca' => [
            'nome' => 'IN³', 'nome_longo' => 'IN³ — INcubator',
            'host' => (string) configuracao('host_publico', (string) cfg('IN3_HOST', 'in.m3d.pro')),
            'pai' => 'm3d.pro',
            'slogan' => 'O que entra na m3d, sai ao cubo.',
        ],
        'niveis' => IN3_NIVEL_ROTULO,
        'categorias' => categorias_publicas($admin),
        'situacoes' => status_disponiveis($admin),
        'repositorios' => [
            'total' => $cob['total'],
            'mapeados' => $cob['mapeados'],
            'por_visibilidade' => $cob['visibilidade'],
            'fonte' => (string) configuracao('fonte_repos', 'não sincronizado'),
        ],
        'busca' => ['motor' => fts_ligado() ? 'fts5' : 'like'],
    ];
}

/** Despacho da API. Devolve sempre JSON; nunca HTML. */
function api_rota(string $caminho): never
{
    $admin = eh_admin();
    $u = usuario_atual();
    $partes = array_values(array_filter(explode('/', trim($caminho, '/'))));
    // /api/v1/<escopo>/<recurso>/<arg>
    $escopo = $partes[2] ?? 'publico';
    $recurso = $partes[3] ?? '';
    $arg = $partes[4] ?? '';

    $publicoSo = ($escopo === 'publico');
    if (!$publicoSo && $u === null) {
        json_saida(['erro' => 'autenticação necessária'], 401);
    }
    if ($escopo === 'painel' && !in_array($u['papel'] ?? '', ['admin', 'curador', 'leitor'], true)) {
        json_saida(['erro' => 'permissão insuficiente'], 403);
    }

    $clareza = clareza_visitante();

    if (metodo() === 'GET') {
        switch ($recurso) {
            case 'portfolio':
                json_saida(api_portfolio($clareza, $admin, [
                    'categoria' => entrada('categoria'),
                    'status' => entrada('situacao'),
                    'nivel' => entrada('nivel'),
                ]));
            case 'projeto':
                $p = $arg !== '' ? projeto_por_slug($arg) : null;
                if ($p && !$admin && (int) $p['mostrar_ao_publico'] !== 1) {
                    $p = null;
                }
                if (!$p) {
                    json_saida(['erro' => 'projeto não encontrado'], 404);
                }
                $p['_repos'] = projeto_repos((int) $p['id']);
                $p['_tech'] = projeto_tech((int) $p['id']);
                $p['_marcos'] = projeto_marcos((int) $p['id']);
                registrar_acesso('api.projeto', (string) $p['slug'], 1);
                json_saida(['api' => 'in3/v1', 'gerado_em' => agora(), 'projeto' => api_projeto($p, $clareza)]);
            case 'busca':
                $r = busca(entrada('q'), $clareza, $admin, ['categoria' => entrada('categoria'), 'status' => entrada('situacao')]);
                registrar_acesso('api.busca', entrada('q'), (int) $r['total']);
                json_saida(['api' => 'in3/v1', 'gerado_em' => agora()] + $r);
            case 'tecnologias':
                json_saida(api_tecnologias($clareza, $admin));
            case 'meta':
                json_saida(api_meta($admin));
            case 'repos':
                if (!$admin) {
                    json_saida(['erro' => 'recurso interno'], 403);
                }
                json_saida(['api' => 'in3/v1', 'repositorios' => consulta(
                    'SELECT nome, visibilidade, linguagem, fork, gh_atualizado_em, commit_total, url
                       FROM repos ORDER BY gh_atualizado_em DESC'
                )]);
        }
        json_saida(['erro' => 'recurso desconhecido', 'recurso' => $recurso], 404);
    }

    if (metodo() === 'POST') {
        if ($publicoSo) {
            // único POST público: pedido de detalhamento
            if ($recurso === 'pedidos') {
                $d = corpo_json() ?: $_POST;
                $r = registrar_pedido($d);
                json_saida($r, $r['ok'] ? 201 : 422);
            }
            if ($recurso === 'acesso') {
                $t = (string) (corpo_json()['token'] ?? entrada('token'));
                $a = usar_acesso($t);
                json_saida($a ? ['ok' => true, 'clareza' => (int) $a['clareza']] : ['ok' => false, 'erro' => 'link inválido ou expirado'], $a ? 200 : 410);
            }
            json_saida(['erro' => 'recurso desconhecido'], 404);
        }
        csrf_verifica();
        if ($recurso === 'projeto' && in_array($u['papel'], ['admin', 'curador'], true)) {
            $d = corpo_json() ?: $_POST;
            $id = salvar_projeto($d);
            if (!empty($d['marcos'])) {
                definir_marcos($id, (string) $d['marcos']);
                indexar_projeto($id);
            }
            json_saida(['ok' => true, 'id' => $id]);
        }
        json_saida(['erro' => 'recurso desconhecido'], 404);
    }

    json_saida(['erro' => 'método não suportado'], 405);
}

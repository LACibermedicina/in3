<?php
declare(strict_types=1);
/**
 * IN³ · feed de notícias.
 *
 * Camada 1 do feed: texto em linguagem humana (título + resumo + corpo).
 * Camada 2 (detalhe_tecnico) só é SERVIDA quando a clareza da requisição é >= 3.
 * Abaixo disso, o campo é removido no servidor — não é escondido por CSS.
 */

function noticias_publicas(int $clareza = 1, int $limite = 12, array $filtro = []): array
{
    $clareza = max(1, min(4, $clareza));
    $limite = max(1, min(60, $limite));
    $sql = "SELECT n.*, p.slug AS projeto_slug, p.emoji AS projeto_emoji, p.titulo AS projeto_titulo
              FROM noticias n LEFT JOIN projetos p ON p.id = n.projeto_id
             WHERE n.publicado = 1 AND n.mostrar_ao_publico = 1
               AND (n.projeto_id IS NULL OR p.mostrar_ao_publico = 1)";
    $par = [];
    if (($filtro['categoria'] ?? '') !== '') {
        $sql .= ' AND n.categoria = :c';
        $par[':c'] = (string) $filtro['categoria'];
    }
    $sql .= ' ORDER BY n.destaque DESC, n.data DESC, n.ordem ASC LIMIT ' . $limite;
    $linhas = consulta($sql, $par);
    foreach ($linhas as &$l) {
        if ($clareza < 3) {
            unset($l['detalhe_tecnico']);        // não sai do servidor
        }
        if ($clareza < 2) {
            unset($l['corpo']);
            unset($l['commit_sha']);
        }
        $l['data_br'] = data_br((string) $l['data']);
        $l['tem_detalhe'] = isset($l['detalhe_tecnico']) && trim((string) $l['detalhe_tecnico']) !== '';
    }
    return $linhas;
}

function noticia_por_slug(string $slug): ?array
{
    $n = um('SELECT * FROM noticias WHERE slug = :s AND publicado = 1 AND mostrar_ao_publico = 1', [':s' => $slug]);
    if (!$n) {
        return null;
    }
    $p = (int) ($n['projeto_id'] ?? 0);
    if ($p > 0) {
        $proj = projeto_por_id($p);
        if (!$proj || (int) $proj['mostrar_ao_publico'] !== 1) {
            return null;                          // notícia de projeto não publicado
        }
    }
    return $n;
}

function noticias_admin(?int $limite = 200): array
{
    return consulta(
        "SELECT n.*, p.titulo AS projeto_titulo, p.mostrar_ao_publico AS projeto_publico
           FROM noticias n LEFT JOIN projetos p ON p.id = n.projeto_id
          ORDER BY n.data DESC LIMIT " . max(1, (int) ($limite ?? 200))
    );
}

function noticia_salvar(array $d, int $id = 0): int
{
    $titulo = trim((string) ($d['titulo'] ?? ''));
    if ($titulo === '') {
        throw new InvalidArgumentException('O título da notícia é obrigatório.');
    }
    $slug = slugificar((string) ($d['slug'] ?? $titulo));
    $projetoId = (int) ($d['projeto_id'] ?? 0);
    $campos = [
        ':slug' => $slug,
        ':titulo' => $titulo,
        ':resumo' => texto_limpo((string) ($d['resumo'] ?? ''), 400),
        ':corpo' => (string) ($d['corpo'] ?? ''),
        ':detalhe' => (string) ($d['detalhe_tecnico'] ?? ''),
        ':categoria' => texto_limpo((string) ($d['categoria'] ?? ''), 80),
        ':projeto' => $projetoId > 0 ? $projetoId : null,
        ':repo' => texto_limpo((string) ($d['repo'] ?? ''), 120),
        ':sha' => texto_limpo((string) ($d['commit_sha'] ?? ''), 40),
        ':autor' => texto_limpo((string) ($d['autor'] ?? ''), 80),
        ':fonte' => in_array(($d['fonte'] ?? 'curadoria'), ['curadoria', 'github', 'manual', 'marco'], true) ? (string) $d['fonte'] : 'curadoria',
        ':pub' => (int) ($d['publicado'] ?? 0) === 1 ? 1 : 0,
        ':mostrar' => (int) ($d['mostrar_ao_publico'] ?? 0) === 1 ? 1 : 0,
        ':nivel' => texto_limpo((string) ($d['nivel_divulgacao'] ?? 'institucional'), 40),
        ':destaque' => (int) ($d['destaque'] ?? 0) === 1 ? 1 : 0,
        ':data' => texto_limpo((string) ($d['data'] ?? agora()), 30),
        ':ordem' => (int) ($d['ordem'] ?? 100),
    ];
    if ($id > 0) {
        $campos[':id'] = $id;
        executa('UPDATE noticias SET slug = :slug, titulo = :titulo, resumo = :resumo, corpo = :corpo,
                    detalhe_tecnico = :detalhe, categoria = :categoria, projeto_id = :projeto, repo = :repo,
                    commit_sha = :sha, autor = :autor, fonte = :fonte, publicado = :pub,
                    mostrar_ao_publico = :mostrar, nivel_divulgacao = :nivel, destaque = :destaque,
                    data = :data, ordem = :ordem WHERE id = :id', $campos);
        auditar('noticia_editada', 'noticias', $id, $titulo);
        return $id;
    }
    executa('INSERT INTO noticias (slug, titulo, resumo, corpo, detalhe_tecnico, categoria, projeto_id, repo,
                commit_sha, autor, fonte, publicado, mostrar_ao_publico, nivel_divulgacao, destaque, data, ordem)
             VALUES (:slug, :titulo, :resumo, :corpo, :detalhe, :categoria, :projeto, :repo, :sha, :autor,
                :fonte, :pub, :mostrar, :nivel, :destaque, :data, :ordem)', $campos);
    $novo = ultimo_id();
    auditar('noticia_criada', 'noticias', $novo, $titulo);
    return $novo;
}

function noticias_estatisticas(): array
{
    return [
        'total'      => (int) escalar('SELECT count(*) FROM noticias'),
        'publicadas' => (int) escalar('SELECT count(*) FROM noticias WHERE publicado = 1 AND mostrar_ao_publico = 1'),
        'categorias' => consulta("SELECT categoria, count(*) AS n FROM noticias WHERE publicado = 1 AND categoria != '' GROUP BY categoria ORDER BY n DESC"),
    ];
}

/* =====================================================================
 * Tradução de atividade do GitHub para linguagem humana.
 * Determinístico e auditável: nenhuma frase é inventada sobre o conteúdo do
 * commit — a mensagem original é sempre preservada em `detalhe_tecnico`.
 * ===================================================================== */

function humanizar_commit(string $msg): array
{
    $m = trim(preg_replace('/\s+/', ' ', $msg));
    $m = preg_replace('/^(fix|feat|chore|docs|refactor|test|style|perf|build|ci|merge)(\([^)]*\))?[:\-]\s*/i', '', $m);
    $minus = mb_strtolower($m);
    $tipo = 'melhoria';
    $frase = 'Ajustes de melhoria contínua.';

    $regras = [
        ['/^(add|adiciona|adicionar|create|cria|criar|inclui|incluir)/u', 'novidade',  'Novidade entregue na plataforma.'],
        ['/^(corrig|fix|ajust|resolv|consert|arrum)/u',                   'correcao',  'Correção aplicada após revisão.'],
        ['/^(document|doc|readme|esclarec|explic)/u',                     'documento', 'Documentação ampliada para consulta da equipe.'],
        ['/^(refator|refactor|reorganiz|limpez|cleanup)/u',               'estrutura', 'Reorganização interna para facilitar a manutenção.'],
        ['/^(test|teste|cobertura)/u',                                    'qualidade', 'Cobertura de testes reforçada.'],
        ['/^(remove|remove|delet|exclui)/u',                              'limpeza',   'Remoção de código obsoleto.'],
        ['/^(merge|junta)/u',                                             'integracao', 'Integração de trabalho já revisado.'],
        ['/^(security|seguran|lgpd|privac)/u',                            'seguranca', 'Reforço de segurança e privacidade.'],
    ];
    foreach ($regras as $r) {
        if (preg_match($r[0], $minus) === 1) {
            $tipo = $r[1];
            $frase = $r[2];
            break;
        }
    }
    $legivel = $m !== '' ? mb_strtoupper(mb_substr($m, 0, 1)) . mb_substr($m, 1) : 'Atualização publicada.';
    return ['tipo' => $tipo, 'frase' => $frase, 'legivel' => $legivel];
}

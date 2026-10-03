<?php
declare(strict_types=1);
/**
 * IN³ · busca com controle de acesso NO SERVIDOR.
 *
 * A consulta nunca "esconde" resultados depois de trazer: ela já entra na SQL
 * com três filtros obrigatórios:
 *   1) escopo do texto <= min(clareza do visitante, nível do projeto)
 *   2) projeto.mostrar_ao_publico = 1 (quando o visitante não é administração)
 *   3) termo obrigatório
 *
 * O limite (1) é escrito como DUAS comparações independentes — e não como
 * MIN(...) — porque em SQLite `MIN` tem forma de agregação e pode devolver
 * resultado inesperado dentro de WHERE. Duas comparações simples são
 * equivalentes e não deixam dúvida: escopo <= clareza E escopo <= teto.
 *
 * Usa FTS5 quando disponível e a varredura em busca_texto como plano B,
 * com EXATAMENTE o mesmo filtro nos dois caminhos.
 */

/** Expressão SQL que converte o nível do projeto em número (1..4). */
const IN3_SQL_TETO = "(CASE p.nivel_divulgacao
        WHEN 'institucional_roadmap' THEN 1
        WHEN 'institucional'         THEN 2
        WHEN 'tecnico'               THEN 3
        ELSE 4 END)";

/** Converte a entrada do visitante em uma consulta MATCH segura. */
function monta_match(string $termo): string
{
    $t = mb_strtolower($termo);
    $t = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $t) ?? '';
    $palavras = array_values(array_filter(preg_split('/\s+/u', trim($t)) ?: [], static fn($w) => $w !== ''));
    $palavras = array_slice($palavras, 0, 8);
    if (!$palavras) {
        return '';
    }
    $saida = [];
    foreach ($palavras as $i => $w) {
        $limpo = preg_replace('/[^\p{L}\p{N}]/u', '', $w) ?? '';
        if ($limpo === '') {
            continue;
        }
        // última palavra recebe prefixo, imitando busca incremental
        $saida[] = ($i === count($palavras) - 1 && mb_strlen($limpo) >= 3)
            ? '"' . $limpo . '"*'
            : '"' . $limpo . '"';
    }
    return implode(' AND ', $saida);
}

/** Normaliza as linhas vindas de qualquer um dos dois motores. */
function busca_normaliza(array $linhas): array
{
    foreach ($linhas as &$l) {
        $l['profundidade'] = (int) $l['profundidade'];
        $l['trecho'] = texto_limpo((string) $l['trecho'], 420);
    }
    return $linhas;
}

function busca(string $termo, int $clareza, bool $admin = false, array $filtro = [], int $limite = 40): array
{
    $termo = trim($termo);
    if (mb_strlen($termo) < 2) {
        return ['termo' => $termo, 'total' => 0, 'resultados' => [], 'motor' => 'nenhum',
                'aviso' => 'Digite ao menos 2 caracteres.'];
    }
    $limite = max(1, min(100, $limite));
    $clareza = max(1, min(4, $clareza));
    $token = monta_match($termo);
    if ($token === '') {
        return ['termo' => $termo, 'total' => 0, 'resultados' => [], 'motor' => 'nenhum',
                'aviso' => 'Termo sem palavras pesquisáveis.'];
    }

    $cat = (string) ($filtro['categoria'] ?? '');
    $st  = (string) ($filtro['status'] ?? '');

    /* ---------------------------------------------------------- motor FTS5 */
    if (fts_ligado()) {
        $sql = 'SELECT b.projeto AS id, b.slug, b.escopo AS profundidade, b.titulo, b.categoria,
                       snippet(busca_fts, 5, \'«\', \'»\', \'…\', 18) AS trecho,
                       bm25(busca_fts) AS pontos
                  FROM busca_fts b
                  JOIN projetos p ON p.id = b.projeto
                 WHERE busca_fts MATCH :q
                   AND b.escopo <= :clr
                   AND b.escopo <= ' . IN3_SQL_TETO . '
                   AND (:admin = 1 OR p.mostrar_ao_publico = 1)
                   AND (:cat = \'\' OR p.categoria = :cat)
                   AND (:st = \'\' OR p.status = :st)
                 ORDER BY p.ordem ASC, b.escopo ASC, pontos ASC
                 LIMIT ' . $limite;
        try {
            $linhas = consulta($sql, [
                ':q' => $token, ':clr' => $clareza, ':admin' => $admin ? 1 : 0,
                ':cat' => $cat, ':st' => $st,
            ]);
            if ($linhas) {
                return ['termo' => $termo, 'total' => count($linhas),
                        'resultados' => busca_normaliza($linhas), 'motor' => 'fts5'];
            }
        } catch (Throwable $e) {
            // MATCH recusado (tokenização/acentos): segue para o plano B
        }
    }

    /* ------------------------------------------------- plano B: busca_texto */
    $palavras = array_slice(preg_split('/\s+/u', mb_strtolower($termo)) ?: [], 0, 6);
    $cond = [];
    $par = [':clr' => $clareza, ':admin' => $admin ? 1 : 0, ':cat' => $cat, ':st' => $st];
    $i = 0;
    foreach ($palavras as $p) {
        $p = preg_replace('/[^\p{L}\p{N}]/u', '', $p) ?? '';
        if ($p === '') {
            continue;
        }
        $k = ':p' . $i++;
        $cond[] = "(lower(b.titulo) LIKE $k OR lower(b.categoria) LIKE $k OR lower(b.corpo) LIKE $k)";
        $par[$k] = '%' . mb_strtolower($p) . '%';
    }
    if (!$cond) {
        return ['termo' => $termo, 'total' => 0, 'resultados' => [], 'motor' => 'nenhum'];
    }
    $sql = 'SELECT b.projeto_id AS id, b.slug, b.escopo AS profundidade, b.titulo, b.categoria,
                   substr(b.corpo, 1, 600) AS trecho, 0 AS pontos
              FROM busca_texto b
              JOIN projetos p ON p.id = b.projeto_id
             WHERE ' . implode(' AND ', $cond) . '
               AND b.escopo <= :clr
               AND b.escopo <= ' . IN3_SQL_TETO . '
               AND (:admin = 1 OR p.mostrar_ao_publico = 1)
               AND (:cat = \'\' OR p.categoria = :cat)
               AND (:st = \'\' OR p.status = :st)
             ORDER BY p.ordem ASC, b.escopo ASC
             LIMIT ' . $limite;
    $linhas = consulta($sql, $par);
    return ['termo' => $termo, 'total' => count($linhas),
            'resultados' => busca_normaliza($linhas), 'motor' => 'like'];
}

/** Marca os termos encontrados no trecho (escape primeiro, realce depois). */
function realcar(string $trecho, string $termo): string
{
    $html = e($trecho);
    $palavras = array_filter(preg_split('/\s+/u', $termo) ?: []);
    foreach ($palavras as $p) {
        $p = preg_replace('/[^\p{L}\p{N}]/u', '', $p) ?? '';
        if (mb_strlen($p) < 3) {
            continue;
        }
        $html = preg_replace_callback(
            '/(' . preg_quote($p, '/') . '[\p{L}]*)/iu',
            static fn($m) => '<mark>' . $m[1] . '</mark>',
            $html
        ) ?? $html;
    }
    // os marcadores de snippet do SQLite viram destaque visual
    return str_replace(['«', '»'], ['<mark>', '</mark>'], $html);
}

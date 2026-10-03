<?php
declare(strict_types=1);
/**
 * =====================================================================
 * IN³ · backlog — sugestões públicas, Kanban por projeto, backlog geral
 * e visibilidade de projeto por usuário (RBAC de escopo).
 *
 * Regra herdada: nada nasce público; a sugestão nasce como 'sugerido'
 * e só entra em trabalho quando o administrador move no Kanban.
 * =====================================================================
 */

/** Colunas do Kanban — a mesma ordem vale para o projeto e para o geral. */
const IN3_KANBAN = [
    'sugerido'  => 'Sugerido',
    'analise'   => 'Em análise',
    'backlog'   => 'Backlog',
    'aprovado'  => 'Aprovado',
    'entregue'  => 'Entregue',
    'recusado'  => 'Recusado',
];

const IN3_KANBAN_COR = [
    'sugerido' => '#3AA3BA', 'analise' => '#7B6CF6', 'backlog' => '#F2994A',
    'aprovado' => '#2A9581', 'entregue' => '#0F7A46', 'recusado' => '#A32B2B',
];

/* ---------------------------------------------------------------- esquema */

function criar_esquema_v4(): void
{
    $sql = [
        "CREATE TABLE IF NOT EXISTS sugestoes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            projeto_id INTEGER NOT NULL DEFAULT 0,
            titulo TEXT NOT NULL DEFAULT '',
            detalhe TEXT NOT NULL DEFAULT '',
            contato TEXT NOT NULL DEFAULT '',
            origem TEXT NOT NULL DEFAULT 'publico',
            status TEXT NOT NULL DEFAULT 'sugerido',
            prioridade INTEGER NOT NULL DEFAULT 50,
            voto INTEGER NOT NULL DEFAULT 0,
            criado_em TEXT NOT NULL DEFAULT '',
            atualizado_em TEXT NOT NULL DEFAULT ''
        )",
        "CREATE TABLE IF NOT EXISTS usuarios_projetos (
            usuario_id INTEGER NOT NULL,
            projeto_id INTEGER NOT NULL,
            nivel INTEGER NOT NULL DEFAULT 4,
            criado_em TEXT NOT NULL DEFAULT '',
            PRIMARY KEY (usuario_id, projeto_id)
        )",
        "CREATE TABLE IF NOT EXISTS i18n_uso (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            idioma TEXT NOT NULL,
            n_textos INTEGER NOT NULL DEFAULT 0,
            fonte TEXT NOT NULL DEFAULT '',
            quando TEXT NOT NULL DEFAULT ''
        )",
        "CREATE INDEX IF NOT EXISTS ix_sug_status ON sugestoes (status, projeto_id)",
        "CREATE INDEX IF NOT EXISTS ix_usp_usuario ON usuarios_projetos (usuario_id)",
    ];
    foreach ($sql as $s) {
        try {
            executar($s);
        } catch (Throwable $e) {
            // banco ainda não existe / travado: a instalação cria depois
        }
    }
    try {
        gravar_config('esquema_versao', defined('IN3_VERSAO') ? IN3_VERSAO : '4.0.0');
    } catch (Throwable $e) {
    }
}

criar_esquema_v4();

/* ------------------------------------------------------------- sugestões */

function sugestao_criar(int $projetoId, string $titulo, string $detalhe, string $contato, string $origem = 'publico'): int
{
    executar('INSERT INTO sugestoes (projeto_id, titulo, detalhe, contato, origem, status, criado_em, atualizado_em)
              VALUES (:p,:t,:d,:c,:o,\'sugerido\',:q,:q)', [
        ':p' => $projetoId, ':t' => limpar_texto($titulo, 160), ':d' => limpar_texto($detalhe, 1200),
        ':c' => limpar_texto($contato, 160), ':o' => $origem, ':q' => agora(),
    ]);
    $id = (int) banco()->lastInsertId();
    auditar('sugestao_criada', 'sugestao#' . $id, 'projeto=' . $projetoId . ' origem=' . $origem);
    return $id;
}

function sugestao_por_id(int $id): ?array
{
    return um('SELECT * FROM sugestoes WHERE id = :i', [':i' => $id]);
}

function sugestao_mover(int $id, string $status): bool
{
    if (!array_key_exists($status, IN3_KANBAN)) {
        return false;
    }
    $n = executar('UPDATE sugestoes SET status = :s, atualizado_em = :q WHERE id = :i',
        [':s' => $status, ':q' => agora(), ':i' => $id]);
    if ($n > 0) {
        auditar('sugestao_movida', 'sugestao#' . $id, 'status=' . $status);
    }
    return $n > 0;
}

function sugestoes_listar(?int $projetoId = null): array
{
    if ($projetoId === null) {
        return consulta('SELECT s.*, p.titulo AS projeto, p.slug, p.cor, p.emoji, p.icone
                         FROM sugestoes s LEFT JOIN projetos p ON p.id = s.projeto_id
                         ORDER BY s.prioridade DESC, s.id DESC');
    }
    return consulta('SELECT s.*, p.titulo AS projeto, p.slug, p.cor, p.emoji, p.icone
                     FROM sugestoes s LEFT JOIN projetos p ON p.id = s.projeto_id
                     WHERE s.projeto_id = :p ORDER BY s.prioridade DESC, s.id DESC', [':p' => $projetoId]);
}

/** Kanban por projeto + visão geral (backlog geral) — o mesmo cartão, dois recortes. */
function kanban_montar(?int $projetoId = null): array
{
    $colunas = [];
    foreach (array_keys(IN3_KANBAN) as $c) {
        $colunas[$c] = ['rotulo' => IN3_KANBAN[$c], 'cor' => IN3_KANBAN_COR[$c], 'cartoes' => []];
    }
    foreach (sugestoes_listar($projetoId) as $s) {
        $st = (string) $s['status'];
        if (!isset($colunas[$st])) {
            $st = 'sugerido';
        }
        $colunas[$st]['cartoes'][] = $s;
    }
    return $colunas;
}

function sugestoes_contagem(): array
{
    $out = [];
    foreach (array_keys(IN3_KANBAN) as $c) {
        $out[$c] = (int) escalar('SELECT count(*) FROM sugestoes WHERE status = :s', [':s' => $c]);
    }
    return $out;
}

/* --------------------------------- visibilidade de projeto por usuário */

/** ids liberados para o usuário. Lista vazia = sem restrição (usa o papel). */
function projetos_do_usuario(int $usuarioId): array
{
    $r = consulta('SELECT projeto_id, nivel FROM usuarios_projetos WHERE usuario_id = :u', [':u' => $usuarioId]);
    $out = [];
    foreach ($r as $l) {
        $out[(int) $l['projeto_id']] = (int) $l['nivel'];
    }
    return $out;
}

function vincular_usuario_projetos(int $usuarioId, array $projetoIds, int $nivel = 4): void
{
    executar('DELETE FROM usuarios_projetos WHERE usuario_id = :u', [':u' => $usuarioId]);
    foreach (array_unique(array_map('intval', $projetoIds)) as $pid) {
        if ($pid > 0) {
            executar('INSERT OR REPLACE INTO usuarios_projetos (usuario_id, projeto_id, nivel, criado_em)
                      VALUES (:u,:p,:n,:q)', [':u' => $usuarioId, ':p' => $pid, ':n' => max(1, min(4, $nivel)), ':q' => agora()]);
        }
    }
    auditar('usuario_projetos', 'usuario#' . $usuarioId, count($projetoIds) . ' projeto(s) · nivel ' . $nivel);
}

/**
 * O projeto pode ser visto pelo visitante atual?
 * Sem sessão (anônimo) → sim, a filtragem de publicação continua no SQL.
 * Com sessão e sem vínculo → sim (herda o papel, comportamento da v3).
 * Com sessão e com vínculo → só os projetos vinculados.
 */
function projeto_autorizado(int $projetoId): bool
{
    $u = usuario_atual();
    if (!$u) {
        return true;
    }
    if (pode('*', $u)) {
        return true;
    }
    $vinculos = projetos_do_usuario((int) $u['id']);
    if (!$vinculos) {
        return true;
    }
    return isset($vinculos[$projetoId]);
}

/** Nível de clareza do usuário apenas quando o projeto está vinculado. */
function nivel_usuario_projeto(int $usuarioId, int $projetoId, int $padrao): int
{
    $v = projetos_do_usuario($usuarioId);
    return isset($v[$projetoId]) ? max(1, min(4, (int) $v[$projetoId])) : $padrao;
}

/* ----------------------------------------------------- estatística painel */

function backlog_resumo(): array
{
    return [
        'total'     => (int) escalar('SELECT count(*) FROM sugestoes'),
        'novos'     => (int) escalar("SELECT count(*) FROM sugestoes WHERE status IN ('sugerido','analise')"),
        'aprovados' => (int) escalar("SELECT count(*) FROM sugestoes WHERE status IN ('aprovado','entregue')"),
        'por_status' => sugestoes_contagem(),
    ];
}

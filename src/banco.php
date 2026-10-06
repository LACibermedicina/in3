<?php
declare(strict_types=1);
/**
 * IN³ · camada de banco — PDO + SQLite (WAL), migrações, detecção de FTS5.
 * Toda consulta usa prepared statements. Nenhuma concatenação de valor em SQL.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $arq = cfg('IN3_BANCO', IN3_DADOS . '/in3.db');
    $dir = dirname((string) $arq);
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível criar o diretório de dados: ' . $dir);
    }
    $pdo = new PDO('sqlite:' . $arq, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    return $pdo;
}

function consulta(string $sql, array $par = []): array
{
    $st = db()->prepare($sql);
    $st->execute($par);
    return $st->fetchAll();
}

function um(string $sql, array $par = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($par);
    $r = $st->fetch();
    return $r === false ? null : $r;
}

function escalar(string $sql, array $par = [])
{
    $st = db()->prepare($sql);
    $st->execute($par);
    $r = $st->fetchColumn();
    return $r === false ? null : $r;
}

function executa(string $sql, array $par = []): int
{
    $st = db()->prepare($sql);
    $st->execute($par);
    return $st->rowCount();
}

function ultimo_id(): int
{
    return (int) db()->lastInsertId();
}

function transacao(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $r = $fn();
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/* ------------------------------------------------------------- migrações */

function esquema_versao(): string
{
    try {
        return (string) (escalar("SELECT valor FROM config WHERE chave='esquema_versao'") ?? '0');
    } catch (Throwable $e) {
        return '0';
    }
}

/** Aplica data/schema.sql e prepara a busca. Idempotente. */
function migrar(): array
{
    $rel = [];
    $arq = IN3_DADOS . '/schema.sql';
    if (!is_file($arq)) {
        throw new RuntimeException('schema.sql ausente em ' . $arq);
    }
    db()->exec((string) file_get_contents($arq));
    $rel[] = 'esquema aplicado';

    // Busca: tabela real (sempre) + índice FTS5 opcional (quando disponível).
    $fts = false;
    try {
        db()->exec(
            "CREATE VIRTUAL TABLE IF NOT EXISTS busca_fts USING fts5(
                projeto UNINDEXED, escopo UNINDEXED, slug UNINDEXED, titulo, categoria, corpo,
                tokenize='unicode61 remove_diacritics 2')"
        );
        $fts = true;
    } catch (Throwable $e) {
        $fts = false;
    }
    executa(
        "INSERT INTO config (chave, valor, atualizado_em) VALUES ('fts5', :v, :q)
         ON CONFLICT(chave) DO UPDATE SET valor = :v, atualizado_em = :q",
        [':v' => $fts ? 'ligado' : 'desligado', ':q' => agora()]
    );
    $rel[] = 'busca FTS5: ' . ($fts ? 'ligada' : 'desligada (usando LIKE)');

    executa(
        "INSERT INTO config (chave, valor, atualizado_em) VALUES ('esquema_versao', :v, :q)
         ON CONFLICT(chave) DO UPDATE SET valor = :v, atualizado_em = :q",
        [':v' => IN3_VERSAO, ':q' => agora()]
    );
    return $rel;
}

function fts_ligado(): bool
{
    try {
        return (string) escalar("SELECT valor FROM config WHERE chave='fts5'") === 'ligado';
    } catch (Throwable $e) {
        return false;
    }
}

function configuracao(string $chave, ?string $padrao = null): ?string
{
    try {
        $v = escalar('SELECT valor FROM config WHERE chave = :c', [':c' => $chave]);
        return $v === null ? $padrao : (string) $v;
    } catch (Throwable $e) {
        return $padrao;
    }
}

function configurar(string $chave, string $valor): void
{
    executa(
        "INSERT INTO config (chave, valor, atualizado_em) VALUES (:c, :v, :q)
         ON CONFLICT(chave) DO UPDATE SET valor = :v, atualizado_em = :q",
        [':c' => $chave, ':v' => $valor, ':q' => agora()]
    );
}

function banco_pronto(): bool
{
    try {
        return escalar("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='projetos'") > 0;
    } catch (Throwable $e) {
        return false;
    }
}

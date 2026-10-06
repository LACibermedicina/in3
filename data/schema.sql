-- =====================================================================
-- IN³ · INcubator · esquema de dados (SQLite 3)
-- Portfólio vivo da incubadora m3d.pro · in.m3d.pro
--
-- Regras de ouro gravadas no esquema:
--   * todo projeto nasce com mostrar_ao_publico = 0;
--   * o nível de divulgação limita a profundidade publicável (1..4);
--   * a tabela de busca guarda o texto EM CAMADAS cumulativas (escopo 1..4),
--     de modo que uma consulta pública jamais alcance o texto interno.
-- =====================================================================

PRAGMA foreign_keys = ON;

-- ---------------------------------------------------------------- config
CREATE TABLE IF NOT EXISTS config (
  chave         TEXT PRIMARY KEY,
  valor         TEXT NOT NULL DEFAULT '',
  atualizado_em TEXT NOT NULL DEFAULT (datetime('now'))
);

-- -------------------------------------------------------------- usuários
-- Nenhuma senha em texto claro: apenas hash (password_hash/password_verify).
CREATE TABLE IF NOT EXISTS usuarios (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  usuario     TEXT NOT NULL UNIQUE,
  nome        TEXT NOT NULL DEFAULT '',
  email       TEXT NOT NULL DEFAULT '',
  papel       TEXT NOT NULL DEFAULT 'leitor' CHECK (papel IN ('admin','curador','leitor')),
  clareza     INTEGER NOT NULL DEFAULT 1 CHECK (clareza BETWEEN 1 AND 4),
  senha_hash  TEXT NOT NULL,
  ativo       INTEGER NOT NULL DEFAULT 1,
  criado_em   TEXT NOT NULL DEFAULT (datetime('now')),
  ultimo_acesso TEXT
);

CREATE TABLE IF NOT EXISTS sessoes (
  token      TEXT PRIMARY KEY,
  usuario_id INTEGER REFERENCES usuarios(id) ON DELETE CASCADE,
  criada_em  TEXT NOT NULL DEFAULT (datetime('now')),
  expira_em  TEXT NOT NULL,
  ip         TEXT NOT NULL DEFAULT '',
  agente     TEXT NOT NULL DEFAULT '',
  csrf       TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS ix_sessoes_expira ON sessoes(expira_em);
CREATE INDEX IF NOT EXISTS ix_sessoes_usuario ON sessoes(usuario_id);

CREATE TABLE IF NOT EXISTS tentativas_login (
  id      INTEGER PRIMARY KEY AUTOINCREMENT,
  quando  TEXT NOT NULL DEFAULT (datetime('now')),
  usuario TEXT NOT NULL DEFAULT '',
  ip      TEXT NOT NULL DEFAULT '',
  sucesso INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS ix_tentativas ON tentativas_login(ip, quando);

-- -------------------------------------------------------- repositórios GH
-- Espelho do inventário GitHub. visibilidade é sempre inferida, nunca inventada:
--   publico  -> confirmado pela API pública
--   privado  -> confirmado por sincronização autenticada (token)
--   pendente -> item declarado na curadoria e ainda não confirmado (sem token)
CREATE TABLE IF NOT EXISTS repos (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  nome            TEXT NOT NULL UNIQUE,
  nome_completo   TEXT NOT NULL DEFAULT '',
  url             TEXT NOT NULL DEFAULT '',
  descricao       TEXT NOT NULL DEFAULT '',
  linguagem       TEXT NOT NULL DEFAULT '',
  fork            INTEGER NOT NULL DEFAULT 0,
  visibilidade    TEXT NOT NULL DEFAULT 'pendente' CHECK (visibilidade IN ('publico','privado','pendente')),
  topics          TEXT NOT NULL DEFAULT '[]',
  tamanho_kb      INTEGER NOT NULL DEFAULT 0,
  gh_criado_em    TEXT NOT NULL DEFAULT '',
  gh_atualizado_em TEXT NOT NULL DEFAULT '',
  gh_commit_sha   TEXT NOT NULL DEFAULT '',
  gh_commit_data  TEXT NOT NULL DEFAULT '',
  gh_commit_msg   TEXT NOT NULL DEFAULT '',
  commit_total    INTEGER NOT NULL DEFAULT 0,
  resumo_readme   TEXT NOT NULL DEFAULT '',
  sincronizado_em TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS ix_repos_vis ON repos(visibilidade);

-- ---------------------------------------------------------------- projetos
CREATE TABLE IF NOT EXISTS projetos (
  id                 INTEGER PRIMARY KEY AUTOINCREMENT,
  slug               TEXT NOT NULL UNIQUE,
  titulo             TEXT NOT NULL,
  emoji              TEXT NOT NULL DEFAULT '🧊',
  icone              TEXT NOT NULL DEFAULT 'cubo',
  categoria          TEXT NOT NULL DEFAULT '',
  status             TEXT NOT NULL DEFAULT 'em incubação',
  ordem              INTEGER NOT NULL DEFAULT 100,
  -- curadoria / liberação
  mostrar_ao_publico INTEGER NOT NULL DEFAULT 0,
  mostrar_link_repo  INTEGER NOT NULL DEFAULT 0,
  nivel_divulgacao   TEXT NOT NULL DEFAULT 'institucional_roadmap'
                     CHECK (nivel_divulgacao IN ('institucional_roadmap','institucional','tecnico','playbook_completo')),
  -- camada 1 · institucional / roadmap
  resumo             TEXT NOT NULL DEFAULT '',
  roadmap            TEXT NOT NULL DEFAULT '[]',
  -- camada 2 · institucional detalhado
  publico_alvo       TEXT NOT NULL DEFAULT '',
  problema           TEXT NOT NULL DEFAULT '',
  solucao            TEXT NOT NULL DEFAULT '',
  como_funciona      TEXT NOT NULL DEFAULT '',
  validacao          TEXT NOT NULL DEFAULT '',
  -- camada 3 · técnico
  arquitetura        TEXT NOT NULL DEFAULT '',
  integracoes        TEXT NOT NULL DEFAULT '',
  -- camada 4 · playbook
  privacidade        TEXT NOT NULL DEFAULT '',
  riscos             TEXT NOT NULL DEFAULT '',
  notas_internas     TEXT NOT NULL DEFAULT '',
  playbook_ref       TEXT NOT NULL DEFAULT '',
  atualizado_em      TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS ix_projetos_pub ON projetos(mostrar_ao_publico, ordem);

CREATE TABLE IF NOT EXISTS projeto_repos (
  projeto_id INTEGER NOT NULL REFERENCES projetos(id) ON DELETE CASCADE,
  repo_id    INTEGER NOT NULL REFERENCES repos(id) ON DELETE CASCADE,
  PRIMARY KEY (projeto_id, repo_id)
) WITHOUT ROWID;

CREATE TABLE IF NOT EXISTS projeto_tech (
  projeto_id INTEGER NOT NULL REFERENCES projetos(id) ON DELETE CASCADE,
  tech       TEXT NOT NULL,
  PRIMARY KEY (projeto_id, tech)
) WITHOUT ROWID;

CREATE TABLE IF NOT EXISTS projeto_marcos (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  projeto_id INTEGER NOT NULL REFERENCES projetos(id) ON DELETE CASCADE,
  data       TEXT NOT NULL DEFAULT '',
  marco      TEXT NOT NULL,
  ordem      INTEGER NOT NULL DEFAULT 100
);
CREATE INDEX IF NOT EXISTS ix_marcos_projeto ON projeto_marcos(projeto_id, ordem);

-- ---------------------------------------------------------------- pedidos
-- Formulário público: é o caminho para destravar as camadas 2, 3 e 4.
CREATE TABLE IF NOT EXISTS pedidos (
  id       INTEGER PRIMARY KEY AUTOINCREMENT,
  quando   TEXT NOT NULL DEFAULT (datetime('now')),
  nome     TEXT NOT NULL DEFAULT '',
  contato  TEXT NOT NULL DEFAULT '',
  projeto  TEXT NOT NULL DEFAULT '',
  nivel    TEXT NOT NULL DEFAULT 'institucional',
  mensagem TEXT NOT NULL DEFAULT '',
  ip       TEXT NOT NULL DEFAULT '',
  estado   TEXT NOT NULL DEFAULT 'novo' CHECK (estado IN ('novo','liberado','recusado','arquivado')),
  respondido_em TEXT
);
CREATE INDEX IF NOT EXISTS ix_pedidos_estado ON pedidos(estado, quando);

-- --------------------------------------------- acessos temporários (token)
-- Liberação de detalhamento (camadas 2/3/4) por link de uso único, com prazo.
CREATE TABLE IF NOT EXISTS acessos (
  token      TEXT PRIMARY KEY,
  pedido_id  INTEGER REFERENCES pedidos(id) ON DELETE SET NULL,
  projeto_id INTEGER REFERENCES projetos(id) ON DELETE CASCADE,
  clareza    INTEGER NOT NULL DEFAULT 2 CHECK (clareza BETWEEN 1 AND 4),
  criado_em  TEXT NOT NULL DEFAULT (datetime('now')),
  expira_em  TEXT NOT NULL,
  usa_ate    TEXT NOT NULL DEFAULT '',
  usos       INTEGER NOT NULL DEFAULT 0,
  max_usos   INTEGER NOT NULL DEFAULT 12,
  estado     TEXT NOT NULL DEFAULT 'ativo' CHECK (estado IN ('ativo','expirado','revogado'))
);
CREATE INDEX IF NOT EXISTS ix_acessos_validade ON acessos(estado, expira_em);

-- ------------------------------------------------------------------ log
CREATE TABLE IF NOT EXISTS acesso_log (
  id        INTEGER PRIMARY KEY AUTOINCREMENT,
  quando    TEXT NOT NULL DEFAULT (datetime('now')),
  rota      TEXT NOT NULL DEFAULT '',
  papel     TEXT NOT NULL DEFAULT 'anonimo',
  usuario_id INTEGER,
  termo     TEXT NOT NULL DEFAULT '',
  resultado INTEGER NOT NULL DEFAULT 0,
  ip        TEXT NOT NULL DEFAULT '',
  agente    TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS ix_log_quando ON acesso_log(quando);

CREATE TABLE IF NOT EXISTS auditoria (
  id        INTEGER PRIMARY KEY AUTOINCREMENT,
  quando    TEXT NOT NULL DEFAULT (datetime('now')),
  usuario_id INTEGER,
  acao      TEXT NOT NULL,
  entidade  TEXT NOT NULL DEFAULT '',
  entidade_id INTEGER,
  detalhe   TEXT NOT NULL DEFAULT '',
  ip        TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS ix_auditoria_quando ON auditoria(quando);

-- ---------------------------------------------------------------- busca
-- Fonte de verdade da busca: uma linha por (projeto, escopo cumulativo).
--   escopo 1 = somente o que a camada 1 pode exibir
--   escopo 2 = camada 1 + 2
--   escopo 3 = camada 1 + 2 + 3
--   escopo 4 = tudo (playbook)
-- A consulta SEMPRE filtra escopo <= menor(clareza do visitante, nível do projeto).
CREATE TABLE IF NOT EXISTS busca_texto (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  projeto_id  INTEGER NOT NULL REFERENCES projetos(id) ON DELETE CASCADE,
  escopo      INTEGER NOT NULL CHECK (escopo BETWEEN 1 AND 4),
  slug        TEXT NOT NULL DEFAULT '',
  titulo      TEXT NOT NULL DEFAULT '',
  categoria   TEXT NOT NULL DEFAULT '',
  corpo       TEXT NOT NULL DEFAULT '',
  UNIQUE (projeto_id, escopo)
);
CREATE INDEX IF NOT EXISTS ix_busca_projeto ON busca_texto(projeto_id, escopo);

INSERT OR IGNORE INTO config (chave, valor) VALUES
  ('esquema_versao', '2.0.0'),
  ('fts5', 'desconhecido');

-- =====================================================================
-- v3 · pedidos de engajamento, feed de notícias, traduções por IA
-- =====================================================================

-- Fonte canônica dos pedidos de engajamento. `pedidos` continua existindo
-- (compatibilidade) e espelha cada linha aqui por gatilho — nenhuma linha duplicada.
CREATE TABLE IF NOT EXISTS pedidos_engajamento (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  origem_id       INTEGER UNIQUE,              -- id em `pedidos`, quando vier do site
  quando          TEXT NOT NULL DEFAULT (datetime('now')),
  nome            TEXT NOT NULL DEFAULT '',
  contato         TEXT NOT NULL DEFAULT '',
  organizacao     TEXT NOT NULL DEFAULT '',
  projeto         TEXT NOT NULL DEFAULT '',
  projeto_id      INTEGER REFERENCES projetos(id) ON DELETE SET NULL,
  nivel_solicitado TEXT NOT NULL DEFAULT 'institucional',
  tipo            TEXT NOT NULL DEFAULT 'detalhamento'
                  CHECK (tipo IN ('detalhamento','parceria','piloto','imprensa','carreira','outro')),
  canal           TEXT NOT NULL DEFAULT 'site'
                  CHECK (canal IN ('site','api','email','evento','indicacao')),
  mensagem        TEXT NOT NULL DEFAULT '',
  ip              TEXT NOT NULL DEFAULT '',
  estado          TEXT NOT NULL DEFAULT 'novo'
                  CHECK (estado IN ('novo','em_analise','liberado','recusado','arquivado')),
  acesso_token    TEXT NOT NULL DEFAULT '',
  respondido_em   TEXT
);
CREATE INDEX IF NOT EXISTS ix_engajamento_estado ON pedidos_engajamento(estado, quando);

CREATE TRIGGER IF NOT EXISTS trg_pedido_espelha_ins
AFTER INSERT ON pedidos BEGIN
  INSERT OR IGNORE INTO pedidos_engajamento
    (origem_id, quando, nome, contato, projeto, nivel_solicitado, mensagem, ip, estado)
  VALUES (new.id, new.quando, new.nome, new.contato, new.projeto, new.nivel,
          new.mensagem, new.ip, new.estado);
END;

CREATE TRIGGER IF NOT EXISTS trg_pedido_espelha_upd
AFTER UPDATE OF estado ON pedidos BEGIN
  UPDATE pedidos_engajamento SET estado = new.estado, respondido_em = new.respondido_em
   WHERE origem_id = new.id;
END;

-- Feed de notícias. `detalhe_tecnico` só é servido a partir da clareza 3.
CREATE TABLE IF NOT EXISTS noticias (
  id               INTEGER PRIMARY KEY AUTOINCREMENT,
  slug             TEXT NOT NULL UNIQUE,
  titulo           TEXT NOT NULL,
  resumo           TEXT NOT NULL DEFAULT '',
  corpo            TEXT NOT NULL DEFAULT '',
  detalhe_tecnico  TEXT NOT NULL DEFAULT '',
  categoria        TEXT NOT NULL DEFAULT '',
  projeto_id       INTEGER REFERENCES projetos(id) ON DELETE SET NULL,
  repo             TEXT NOT NULL DEFAULT '',
  commit_sha       TEXT NOT NULL DEFAULT '',
  autor            TEXT NOT NULL DEFAULT '',
  fonte            TEXT NOT NULL DEFAULT 'curadoria'
                   CHECK (fonte IN ('curadoria','github','manual','marco')),
  publicado        INTEGER NOT NULL DEFAULT 0,
  mostrar_ao_publico INTEGER NOT NULL DEFAULT 0,
  nivel_divulgacao TEXT NOT NULL DEFAULT 'institucional',
  destaque         INTEGER NOT NULL DEFAULT 0,
  data             TEXT NOT NULL DEFAULT (datetime('now')),
  ordem            INTEGER NOT NULL DEFAULT 100
);
CREATE INDEX IF NOT EXISTS ix_noticias_pub ON noticias(publicado, mostrar_ao_publico, data DESC);

-- Cache de traduções. A chave é o hash do próprio texto de origem: um visitante
-- só consegue encomendar a tradução de um texto que já recebeu, portanto este
-- cache não é um caminho possível de vazamento de conteúdo interno.
CREATE TABLE IF NOT EXISTS traducoes (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  idioma          TEXT NOT NULL,
  origem_hash     TEXT NOT NULL,
  texto_original  TEXT NOT NULL,
  texto_traduzido TEXT NOT NULL,
  motor           TEXT NOT NULL DEFAULT 'dicionario',
  criado_em       TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE (idioma, origem_hash)
);
CREATE INDEX IF NOT EXISTS ix_traducoes_idioma ON traducoes(idioma);

INSERT OR IGNORE INTO config (chave, valor) VALUES
  ('idioma_padrao', 'pt-BR'),
  ('traducao_motor', 'dicionario'),
  ('traducao_modelo', 'gpt-4o-mini'),
  ('esquema_versao', '3.0.0');

#!/usr/bin/env node
/**
 * IN³ · servidor opcional (zero dependências).
 *
 * Serve o site público E aplica a política de acesso NO SERVIDOR:
 *  - GET  /api/public/portfolio      → somente itens com mostrar_ao_publico = true
 *  - POST /api/requests              → recebe pedidos de detalhes (grava em data/requests.json)
 *  - POST /api/admin/login           → valida ADMIN_PASSWORD (hash SHA-256 + token em memória)
 *  - GET  /api/admin/portfolio       → exige token; devolve TODOS os itens
 *  - POST /api/admin/project         → exige token; cria/atualiza item e reescreve portfolio.full.json
 *  - POST /api/github/refresh        → exige token; coleta usando GITHUB_TOKEN (nunca exposto ao browser)
 *
 * Variáveis de ambiente: ADMIN_PASSWORD, GITHUB_TOKEN (opcional), PORT (padrão 8787)
 */
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import url from 'node:url';
import crypto from 'node:crypto';

const ROOT = path.resolve(path.dirname(url.fileURLToPath(import.meta.url)), '..');
const PORT = Number(process.env.PORT || 8787);
const SENHA = process.env.ADMIN_PASSWORD || '';
const tokens = new Set();
const sha = s => crypto.createHash('sha256').update(s).digest('hex');
const ler = f => { try { return JSON.parse(fs.readFileSync(path.join(ROOT, f), 'utf8')); } catch { return null; } };
const gravar = (f, o) => fs.writeFileSync(path.join(ROOT, f), JSON.stringify(o, null, 2));

/* Persistência: SQLite nativo (node:sqlite) — zero dependências externas.
   Se o runtime não expuser node:sqlite, cai automaticamente para JSON. */
let banco = null;
try {
  const { DatabaseSync } = await import('node:sqlite');
  fs.mkdirSync(path.join(ROOT, 'data'), { recursive: true });
  banco = new DatabaseSync(path.join(ROOT, 'data/in3.db'));
  banco.exec('CREATE TABLE IF NOT EXISTS pedidos (id INTEGER PRIMARY KEY AUTOINCREMENT, quando TEXT, nome TEXT, contato TEXT, projeto TEXT, nivel TEXT, mensagem TEXT, ip TEXT)');
  banco.exec('CREATE TABLE IF NOT EXISTS itens (id TEXT PRIMARY KEY, titulo TEXT, emoji TEXT, categoria TEXT, status TEXT, mostrar_ao_publico INTEGER, mostrar_link_repo INTEGER, repos TEXT, tech_extra TEXT, playbook TEXT, atualizado TEXT)');
} catch (e) { console.warn('  ! node:sqlite indisponível (' + e.message + ') — usando data/requests.json'); }

const MIME = { '.html': 'text/html; charset=utf-8', '.css': 'text/css; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.json': 'application/json; charset=utf-8', '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.webp': 'image/webp', '.ico': 'image/x-icon', '.md': 'text/markdown; charset=utf-8' };

function json(res, code, obj) {
  res.writeHead(code, { 'content-type': 'application/json; charset=utf-8', 'cache-control': 'no-store' });
  res.end(JSON.stringify(obj));
}
async function corpo(req) {
  const chunks = [];
  for await (const c of req) chunks.push(c);
  try { return JSON.parse(Buffer.concat(chunks).toString('utf8') || '{}'); } catch { return {}; }
}
function autorizado(req) {
  const t = req.headers['x-in3-token'];
  return !!t && tokens.has(t);
}

const server = http.createServer(async (req, res) => {
  const u = new URL(req.url, `http://${req.headers.host}`);
  const rota = u.pathname;

  /* ---------- API ---------- */
  if (rota === '/api/public/portfolio' && req.method === 'GET') {
    const pub = ler('data/portfolio.json');
    if (!pub) return json(res, 503, { erro: 'build não executado: rode npm run build' });
    const somentePublicos = pub.projetos.filter(p => p.mostrar_ao_publico !== false);
    return json(res, 200, { ...pub, projetos: somentePublicos, meta: { ...pub.meta, aplicado_no_servidor: true } });
  }

  if (rota === '/api/health') return json(res, 200, { ok: true, modo: 'servidor', itens: (ler('data/portfolio.json')?.projetos || []).length });

  if (rota === '/api/requests' && req.method === 'POST') {
    const dados = await corpo(req);
    if (!dados.nome || !dados.mensagem) return json(res, 400, { erro: 'campos obrigatórios: nome, mensagem' });
    const quando = new Date().toISOString();
    const arquivo = ler('data/requests.json') || { pedidos: [] };
    arquivo.pedidos.unshift({ ...dados, quando, ip: req.socket.remoteAddress });
    gravar('data/requests.json', arquivo);
    if (banco) banco.prepare('INSERT INTO pedidos (quando,nome,contato,projeto,nivel,mensagem,ip) VALUES (?,?,?,?,?,?,?)')
      .run(quando, dados.nome || '', dados.contato || '', dados.projeto || '', dados.nivel || '', dados.mensagem || '', req.socket.remoteAddress || '');
    return json(res, 201, { ok: true, total: arquivo.pedidos.length, persistencia: banco ? 'sqlite' : 'json' });
  }

  if (rota === '/api/admin/login' && req.method === 'POST') {
    const { senha } = await corpo(req);
    if (!senha || sha(senha) !== sha(SENHA)) return json(res, 401, { erro: 'senha inválida' });
    const token = crypto.randomBytes(24).toString('hex');
    tokens.add(token);
    setTimeout(() => tokens.delete(token), 1000 * 60 * 60);
    return json(res, 200, { token, expira_em_min: 60 });
  }

  if (rota === '/api/admin/requests' && req.method === 'GET') {
    if (!autorizado(req)) return json(res, 401, { erro: 'token inválido' });
    if (banco) return json(res, 200, { persistencia: 'sqlite', pedidos: banco.prepare('SELECT * FROM pedidos ORDER BY id DESC LIMIT 200').all() });
    return json(res, 200, { persistencia: 'json', ...(ler('data/requests.json') || { pedidos: [] }) });
  }

  if (rota === '/api/admin/portfolio' && req.method === 'GET') {
    if (!autorizado(req)) return json(res, 401, { erro: 'token inválido' });
    const full = ler('data/portfolio.full.json');
    return json(res, 200, full || { projetos: [] });
  }

  if (rota === '/api/admin/project' && req.method === 'POST') {
    if (!autorizado(req)) return json(res, 401, { erro: 'token inválido' });
    const item = await corpo(req);
    if (!item.id || !item.titulo) return json(res, 400, { erro: 'id e titulo são obrigatórios' });
    const full = ler('data/portfolio.full.json') || { meta: {}, brand: {}, github: {}, projetos: [] };
    const i = full.projetos.findIndex(p => p.id === item.id);
    if (i >= 0) full.projetos[i] = { ...full.projetos[i], ...item };
    else full.projetos.push({ ...item, mostrar_ao_publico: !!item.mostrar_ao_publico });
    gravar('data/portfolio.full.json', full);
    if (banco) banco.prepare('INSERT INTO itens (id,titulo,emoji,categoria,status,mostrar_ao_publico,mostrar_link_repo,repos,tech_extra,playbook,atualizado) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON CONFLICT(id) DO UPDATE SET titulo=excluded.titulo, emoji=excluded.emoji, categoria=excluded.categoria, status=excluded.status, mostrar_ao_publico=excluded.mostrar_ao_publico, mostrar_link_repo=excluded.mostrar_link_repo, repos=excluded.repos, tech_extra=excluded.tech_extra, playbook=excluded.playbook, atualizado=excluded.atualizado')
      .run(item.id, item.titulo, item.emoji || '🧊', item.categoria || 'Geral', item.status || 'backlog', item.mostrar_ao_publico ? 1 : 0, item.mostrar_link_repo ? 1 : 0, JSON.stringify(item.repos || []), JSON.stringify(item.tech_extra || []), item.playbook || '', new Date().toISOString());
    return json(res, 200, { ok: true, total: full.projetos.length, persistencia: banco ? 'sqlite' : 'json' });
  }

  if (rota === '/api/github/refresh' && req.method === 'POST') {
    if (!autorizado(req)) return json(res, 401, { erro: 'token inválido' });
    if (!process.env.GITHUB_TOKEN) return json(res, 400, { erro: 'defina GITHUB_TOKEN no servidor' });
    try {
      const r = await fetch('https://api.github.com/user/repos?per_page=100&sort=pushed', {
        headers: { authorization: `Bearer ${process.env.GITHUB_TOKEN}`, accept: 'application/vnd.github+json', 'user-agent': 'in3-incubator' }
      });
      const repos = await r.json();
      if (!Array.isArray(repos)) throw new Error('resposta inesperada do GitHub');
      gravar('data/github.repos.cache.json', repos.map(x => ({
        name: x.name, description: x.description, language: x.language, topics: x.topics || [],
        size: x.size, html_url: x.html_url, fork: x.fork, archived: x.archived,
        created_at: x.created_at, pushed_at: x.pushed_at, default_branch: x.default_branch
      })));
      return json(res, 200, { ok: true, repos: repos.length, proximo_passo: 'npm run build' });
    } catch (e) { return json(res, 502, { erro: String(e.message) }); }
  }

  /* ---------- estáticos ---------- */
  let arquivo = rota === '/' ? '/index.html' : rota;
  const alvo = path.join(ROOT, path.normalize(arquivo).replace(/^(\.\.[/\\])+/, ''));
  if (!alvo.startsWith(ROOT) || !fs.existsSync(alvo) || fs.statSync(alvo).isDirectory()) {
    res.writeHead(404, { 'content-type': 'text/html; charset=utf-8' });
    return res.end('<h1>404</h1><p><a href="/">voltar ao portfólio</a></p>');
  }
  res.writeHead(200, { 'content-type': MIME[path.extname(alvo)] || 'application/octet-stream' });
  fs.createReadStream(alvo).pipe(res);
});

server.listen(PORT, () => {
  console.log(`IN³ no ar em http://localhost:${PORT}`);
  console.log(`  site público ......... http://localhost:${PORT}/`);
  console.log(`  painel administrativo  http://localhost:${PORT}/admin.html`);
  if (!SENHA) console.log('  ! senha de demonstração ativa — defina ADMIN_PASSWORD antes de publicar');
});

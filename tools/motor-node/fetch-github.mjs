#!/usr/bin/env node
/**
 * IN³ · coleta GitHub (server-side).
 * Uso: node tools/fetch-github.mjs [--conta LACibermedicina] [--token $GITHUB_TOKEN]
 * Grava: data/github.repos.cache.json, data/github.readmes.cache.json, data/github.commits.cache.json
 * O token NUNCA é enviado ao navegador nem embutido no HTML público.
 */
import fs from 'node:fs';
import path from 'node:path';
import url from 'node:url';

const ROOT = path.resolve(path.dirname(url.fileURLToPath(import.meta.url)), '..');
const args = process.argv.slice(2);
const pegaArg = (nome, padrao) => { const i = args.indexOf(nome); return i >= 0 ? args[i + 1] : padrao; };
const conta = pegaArg('--conta', process.env.GITHUB_USER || 'LACibermedicina');
const token = pegaArg('--token', process.env.GITHUB_TOKEN || '');
const cab = { accept: 'application/vnd.github+json', 'user-agent': 'in3-incubator' };
if (token) cab.authorization = `Bearer ${token}`;

const get = async (u, raw = false) => {
  const r = await fetch(u, { headers: cab });
  if (!r.ok) throw new Error(`HTTP ${r.status} em ${u}`);
  return raw ? r.text() : r.json();
};

const repos = await get(`https://api.github.com/users/${conta}/repos?per_page=100&sort=pushed`);
const limpos = repos.map(x => ({
  name: x.name, full_name: x.full_name, description: x.description, language: x.language,
  topics: x.topics || [], size: x.size, html_url: x.html_url, homepage: x.homepage,
  fork: x.fork, archived: x.archived, created_at: x.created_at, pushed_at: x.pushed_at,
  default_branch: x.default_branch, stargazers_count: x.stargazers_count
}));
fs.writeFileSync(path.join(ROOT, 'data/github.repos.cache.json'), JSON.stringify(limpos, null, 2));
console.log(`✔ ${limpos.length} repositórios coletados de ${conta}`);

const readmes = {};
const commits = {};
const alvos = limpos.filter(r => !r.fork).sort((a, b) => (b.pushed_at || '').localeCompare(a.pushed_at || '')).slice(0, 20);

for (const r of alvos) {
  for (const nome of ['README.md', 'readme.md', 'Readme.md']) {
    try {
      readmes[r.name] = await get(`https://raw.githubusercontent.com/${r.full_name}/HEAD/${nome}`, true);
      break;
    } catch { /* segue tentando */ }
  }
  try {
    const c = await get(`https://api.github.com/repos/${r.full_name}/commits?per_page=100`);
    commits[r.name] = c.map(x => ({
      sha: x.sha.slice(0, 8), date: x.commit.author.date,
      msg: (x.commit.message || '').split('\n')[0].slice(0, 140),
      author: x.commit.author?.name || ''
    }));
  } catch { commits[r.name] = []; }
  console.log(`  · ${r.name}: ${readmes[r.name] ? 'README ✓' : 'README —'} / ${commits[r.name].length} commits`);
}

fs.writeFileSync(path.join(ROOT, 'data/github.readmes.cache.json'), JSON.stringify(readmes, null, 2));
fs.writeFileSync(path.join(ROOT, 'data/github.commits.cache.json'), JSON.stringify(commits, null, 2));
console.log('✔ caches gravados. Rode: npm run build');

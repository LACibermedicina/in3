#!/usr/bin/env node
/**
 * IN³ · publish — porta de entrada do que sai do painel administrativo.
 *
 * Uso:
 *   node tools/publish.mjs caminho/para/portfolio.full.json
 *
 * Lê o arquivo exportado pelo painel (contém itens privados + checkbox),
 * grava data/portfolio.source.json (curadoria, com os checkboxes) e roda o build.
 * É AQUI que o checkbox "mostrar ao público" vira a única fonte de verdade:
 * o que estiver marcado entra em index.html; o resto não existe no site público.
 */
import fs from 'node:fs';
import path from 'node:path';
import url from 'node:url';
import { execFileSync } from 'node:child_process';

const ROOT = path.resolve(path.dirname(url.fileURLToPath(import.meta.url)), '..');
const entrada = process.argv[2];
if (!entrada || !fs.existsSync(entrada)) {
  console.error('✖ informe o arquivo exportado pelo painel: node tools/publish.mjs portfolio.full.json');
  process.exit(1);
}

const novo = JSON.parse(fs.readFileSync(entrada, 'utf8'));
const fonte = JSON.parse(fs.readFileSync(path.join(ROOT, 'data/portfolio.source.json'), 'utf8'));

const porId = new Map(fonte.projetos.map(p => [p.id, p]));
const resultado = [];

for (const item of novo.projetos || []) {
  const existente = porId.get(item.id) || {};
  resultado.push({
    id: item.id,
    titulo: item.titulo,
    emoji: item.emoji || '🧩',
    categoria: item.categoria || 'Geral',
    status: item.status || 'backlog',
    mostrar_ao_publico: !!item.mostrar_ao_publico,
    mostrar_link_repo: !!item.mostrar_link_repo,
    repos: item.repos || [],
    tech_extra: item.tech_extra || existente.tech_extra || [],
    linha_do_tempo: item.linha_do_tempo || existente.linha_do_tempo || [],
    imagem_svg: item.imagem_svg || existente.imagem_svg || 'generico',
    playbook: item.playbook || existente.playbook || null
  });
}

fonte.projetos = resultado;
fonte.gerado_em = new Date().toISOString().slice(0, 10);
fs.writeFileSync(path.join(ROOT, 'data/portfolio.source.json'), JSON.stringify(fonte, null, 2));

const publicos = resultado.filter(r => r.mostrar_ao_publico).length;
console.log(`✔ curadoria atualizada: ${resultado.length} itens, ${publicos} marcados para o público.`);

const npmCmd = process.platform === 'win32' ? 'npm.cmd' : 'npm';
execFileSync(npmCmd, ['run', 'build'], { cwd: ROOT, stdio: 'inherit' });
execFileSync(npmCmd, ['run', 'verify'], { cwd: ROOT, stdio: 'inherit' });

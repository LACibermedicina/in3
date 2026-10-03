#!/usr/bin/env node
/**
 * IN³ · verificação de privacidade e integridade.
 * Sai com código 1 se qualquer item privado vazar para o site público.
 */
import fs from 'node:fs';
import path from 'node:path';
import url from 'node:url';

const ROOT = path.resolve(path.dirname(url.fileURLToPath(import.meta.url)), '..');
const p = (...a) => path.join(ROOT, ...a);
const falhas = [];
const ok = [];

const fonte = JSON.parse(fs.readFileSync(p('data/portfolio.source.json'), 'utf8'));
const index = fs.readFileSync(p('index.html'), 'utf8');
const admin = fs.readFileSync(p('admin.html'), 'utf8');
const pub = JSON.parse(fs.readFileSync(p('data/portfolio.json'), 'utf8'));
const full = JSON.parse(fs.readFileSync(p('data/portfolio.full.json'), 'utf8'));

const privados = fonte.projetos.filter(x => !x.mostrar_ao_publico);
const publicos = fonte.projetos.filter(x => x.mostrar_ao_publico);

// 1. Nenhum título de projeto privado dentro do HTML público
for (const pr of privados) {
  if (index.includes(pr.titulo)) falhas.push(`TÍTULO PRIVADO EXPOSTO em index.html: "${pr.titulo}"`);
  if (pub.projetos.some(x => x.id === pr.id)) falhas.push(`PROJETO PRIVADO em data/portfolio.json: ${pr.id}`);
}
ok.push(`nenhum dos ${privados.length} títulos privados aparece em index.html`);

// 2. HTML público não contém URL de repositório quando mostrar_link_repo=false
const urlsVazadas = (index.match(/https:\/\/github\.com\/LACibermedicina\/[A-Za-z0-9._-]+/g) || []);
const permitidas = publicos.filter(x => x.mostrar_link_repo).flatMap(x => x.repos_url || []);
const indevidas = urlsVazadas.filter(u => !permitidas.includes(u));
if (indevidas.length) falhas.push(`URL de repositório exposta sem permissão: ${[...new Set(indevidas)].join(', ')}`);
ok.push(`links de repositório no HTML público: ${urlsVazadas.length} (permitidos: ${permitidas.length})`);

// 3. Contagens coerentes
if (pub.projetos.length !== publicos.length) falhas.push(`portfolio.json tem ${pub.projetos.length} projetos, esperado ${publicos.length}`);
if (full.projetos.length !== fonte.projetos.length) falhas.push(`portfolio.full.json tem ${full.projetos.length}, esperado ${fonte.projetos.length}`);
if (!pub.noticias.every(n => pub.projetos.some(x => x.id === n.projeto_id))) falhas.push('notícia pública referencia projeto não público');
ok.push(`portfolio.json: ${pub.projetos.length} públicos · portfolio.full.json: ${full.projetos.length} itens (uso interno)`);

// 4. Estruturas obrigatórias presentes
for (const campo of ['meta', 'brand', 'github', 'politica_acesso', 'estatisticas', 'projetos', 'noticias', 'heatmap', 'tecnologias', 'calendario']) {
  if (!(campo in pub)) falhas.push(`campo ausente em portfolio.json: ${campo}`);
}
if (pub.heatmap.length !== 182) falhas.push(`heatmap com ${pub.heatmap.length} dias, esperado 182`);

// 5. Camadas de leitura por projeto
for (const pr of pub.projetos) {
  if (!pr.resumo_publico || pr.resumo_publico.length < 40) falhas.push(`${pr.id}: resumo público curto ausente`);
  if (!pr.resumo_tecnico || pr.resumo_tecnico.length < 40) falhas.push(`${pr.id}: resumo técnico ausente`);
}
ok.push(`camada humana + técnica presentes nos ${pub.projetos.length} projetos públicos`);

// 6. App autocontido (zero dependências externas)
if (/<script[^>]+src=["']https?:/.test(index)) falhas.push('index.html carrega script externo (deve ser autocontido)');
if (/<link[^>]+href=["']https?:/.test(index)) falhas.push('index.html carrega CSS/fonte externa');
if (!index.includes('window.__IN3__')) falhas.push('index.html sem dados embutidos');
ok.push('index.html autocontido (sem rede para renderizar)');

console.log('\n— Verificação IN³ —');
for (const o of ok) console.log(`  ✔ ${o}`);
if (falhas.length) {
  console.log('\n  ✖ FALHAS:');
  for (const f of falhas) console.log(`    - ${f}`);
  process.exit(1);
}
console.log('  ✔ nenhum vazamento: política "mostrar ao público" aplicada\n');

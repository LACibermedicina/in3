#!/usr/bin/env node
/**
 * IN³ · build do portfólio
 * Zero dependências externas (apenas módulos nativos do Node >= 18).
 *
 * O que faz:
 *  1. Lê data/portfolio.source.json (curadoria) + caches do GitHub (repos/readmes/commits).
 *  2. Interpreta cada projeto: camada HUMANA (linguagem simples) e camada TÉCNICA.
 *     - Sem chave de IA -> interpretador determinístico (dicionário + estatísticas reais).
 *     - Com OPENAI_API_KEY (ou compatível OPENAI_BASE_URL) -> reescreve as camadas com IA.
 *  3. Monta feed de notícias, mapa de calor de commits, badges de tecnologia e timeline.
 *  4. FILTRA por 'mostrar_ao_publico'. Somente itens públicos entram em:
 *        - index.html  (site público)
 *        - data/portfolio.json (fonte pública)
 *     O arquivo data/portfolio.full.json (com itens privados) NUNCA é embutido no HTML público.
 *  5. Injeta os dados dentro dos templates -> index.html e admin.html autocontidos.
 */
import fs from 'node:fs';
import path from 'node:path';
import url from 'node:url';

const ROOT = path.resolve(path.dirname(url.fileURLToPath(import.meta.url)), '..');
const p = (...a) => path.join(ROOT, ...a);
const j = (f, fallback) => {
  try { return JSON.parse(fs.readFileSync(p(f), 'utf8')); } catch { return fallback; }
};
const kb = n => Math.round((n || 0));
const fmtKb = n => (n >= 1024 ? (n / 1024).toFixed(1).replace('.', ',') + ' MB' : n + ' KB');

const fonte = j('data/portfolio.source.json', null);
if (!fonte) { console.error('✖ data/portfolio.source.json não encontrado.'); process.exit(1); }

const repoCache = j('data/github.repos.cache.json', []);
const readmeCache = j('data/github.readmes.cache.json', {});
const commitCache = j('data/github.commits.cache.json', {});

const reposByName = new Map(repoCache.map(r => [r.name, r]));

/* ------------------------------------------------------------------ *
 * 1. Interpretador determinístico (fallback sem IA)                   *
 * ------------------------------------------------------------------ */
const LEXICO = [
  { re: /(telemed|teleconsult|video.?clinic|webrtc|cyphermed)/i, emoji: '🩺', humano: 'consulta e acompanhamento de pacientes a distância' },
  { re: /(irpf|receita.?federal|fiscal|imposto|gcap|\.dec|ocr)/i, emoji: '🧾', humano: 'organização de documentos e impostos, com leitura automática dos papéis' },
  { re: /(catalog|shop|loja|product|produto|cart|pedido)/i, emoji: '🛍️', humano: 'vitrine de produtos e pedidos' },
  { re: /(revalida|quest|prova|exam|estudo|trilha|flashcard)/i, emoji: '🎓', humano: 'estudo dirigido e banco de questões' },
  { re: /(wifi|rssi|scan|sensor|ble|bluetooth|mapa|mapper)/i, emoji: '📶', humano: 'leitura de sinais do ambiente para desenhar mapas' },
  { re: /(certificad|certificate|evento)/i, emoji: '📜', humano: 'emissão automática de certificados de cursos e eventos' },
  { re: /(video|cirurg|surg|stream)/i, emoji: '🎥', humano: 'acervo de vídeos e transmissão' },
  { re: /(esporte|sport|atleta|reabilita|fisio)/i, emoji: '🏃', humano: 'saúde, desempenho e reabilitação' },
  { re: /(vision|vlm|llm|model|ia|ai|ml|machine.?learning|onnx|offline)/i, emoji: '🧠', humano: 'modelos de inteligência artificial' },
  { re: /(api|backend|server|fastapi|express|next)/i, emoji: '🔌', humano: 'serviços que conversam entre si' },
  { re: /(dashboard|painel|admin|gest|indicador|metric)/i, emoji: '📊', humano: 'painéis de acompanhamento' },
  { re: /(site|landing|p[áa]gina|portf[óo]lio|show|social)/i, emoji: '✨', humano: 'presença digital e apresentação' },
  { re: /(lgpd|hipaa|privacidade|seguran[çc]a|cripto)/i, emoji: '🔐', humano: 'segurança e privacidade dos dados' },
];

const LINGUAGEM_HUMANA = {
  TypeScript: 'aplicação web moderna',
  JavaScript: 'aplicação web moderna',
  Python: 'scripts e análise de dados',
  PHP: 'site dinâmico clássico',
  Shell: 'rotinas de backup e automação',
  Ballerina: 'serviço de integração leve',
  HTML: 'página web',
  CSS: 'estilo visual',
};

function detectarTemas(texto) {
  const achados = [];
  const t = texto || '';
  for (const item of LEXICO) if (item.re.test(t)) achados.push(item);
  return achados.slice(0, 3);
}

function limparReadme(txt) {
  if (!txt) return '';
  return txt
    .replace(/```[\s\S]*?```/g, ' ')
    .replace(/!\[[^\]]*\]\([^)]*\)/g, ' ')
    .replace(/\[([^\]]*)\]\([^)]*\)/g, '$1')
    .replace(/https?:\/\/\S+/g, ' ')
    .replace(/www\.[^\s]+/g, ' ')
    .replace(/^#{1,6}\s*/gm, '')
    .replace(/[*_>`|]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function primeirasFrases(txt, n = 2) {
  const limpo = limparReadme(txt);
  if (!limpo) return '';
  const frases = limpo.split(/(?<=[.!?])\s+/).filter(f => f.length > 25);
  return frases.slice(0, n).join(' ').slice(0, 320);
}

function tecnologiasDoReadme(txt) {
  const t = txt || '';
  const deps = new Set();
  (t.match(/(?:import|from)\s+['"]([a-z0-9@/._-]{2,40})['"]/gi) || []).forEach(m => {
    const nome = (m.match(/['"]([^'"]+)['"]/) || [])[1];
    if (nome && !nome.startsWith('.') && !nome.startsWith('node:')) deps.add(nome.split('/')[0]);
  });
  (t.match(/^\s*[-*]\s*(?:npm i|npm install|pip install|yarn add|pnpm add)\s+([^\n]+)/gim) || []).forEach(m => {
    m.replace(/^\s*[-*]\s*(?:npm i|npm install|pip install|yarn add|pnpm add)\s+/i, '')
      .split(/[\s,]+/).filter(Boolean).slice(0, 6).forEach(d => deps.add(d.replace(/[`'"]/g, '')));
  });
  return [...deps].filter(d => d.length > 1).slice(0, 8);
}

function interpretarDeterministico(proj, ctx) {
  const temas = detectarTemas([
    proj.titulo, proj.categoria, proj.tech_extra?.join(' '), ctx.descricoes, ctx.readme
  ].join(' '));
  const t2 = temas.slice(0, 2);
  const emojis = [...new Set(t2.map(t => t.emoji))].join('');
  const alvo = t2.map(t => t.humano);
  const frases = primeirasFrases(ctx.readme, 1);

  const primeira = ctx.primeiroPush?.slice(0, 10);
  const ultima = ctx.ultimoPush?.slice(0, 10);
  const langs = Object.entries(ctx.linguagens).sort((a, b) => b[1] - a[1]).map(([k]) => k);
  const langHumana = langs.length ? (LINGUAGEM_HUMANA[langs[0]] || 'software') : 'software';

  const nomeCurto = proj.titulo.split('—')[0].trim();
  const humano = [
    `${emojis || '🧊'} ${nomeCurto} é uma frente de ${proj.categoria} dentro da IN³ — ${langHumana}${alvo.length ? ', pensada para ' + alvo.join(' e ') : ''}.`,
    ctx.commits.length
      ? `O histórico já reúne ${ctx.commits.length} registro(s) de trabalho${primeira ? `, de ${primeira}` : ''}${ultima ? ` a ${ultima}` : ''}.`
      : (ultima ? `Há atividade registrada em ${ultima}.` : ''),
    frases ? `Nas palavras dos próprios autores: “${frases}”` : '',
    proj.status === 'ativo' ? 'Está em atividade agora.' :
      proj.status === 'em incubação' ? 'Está em incubação dentro da IN³.' :
      proj.status === 'protótipo' ? 'Está em fase de protótipo.' :
      proj.status === 'pesquisa' ? 'É frente de pesquisa aberta.' :
      proj.status === 'entregue' ? 'Já foi entregue.' : 'Está em backlog.'
  ].filter(Boolean).join(' ');

  const tecnico = [
    `Repositórios: ${proj.repos.map(r => `\`${r}\``).join(', ')} (nomes de repositório — endereços liberados sob solicitação).`,
    `Linguagens detectadas: ${langs.length ? langs.join(', ') : 'não detectada pelo GitHub'}.`,
    proj.tech_extra?.length ? `Stack/domínio: ${proj.tech_extra.join(', ')}.` : '',
    `Volume versionado: ${fmtKb(ctx.tamanhoKb)}; ${ctx.commits.length} commit(s) lidos na API do GitHub; último push ${ultima || 'sem registro'}.`,
    tecnologiasDoReadme(ctx.readme).length ? `Dependências citadas no README: ${tecnologiasDoReadme(ctx.readme).join(', ')}.` : '',
    ctx.topicos?.length ? `Tópicos do GitHub: ${ctx.topicos.join(', ')}.` : '',
    'Camada técnica detalhada (arquitetura, decisões e riscos) liberada sob solicitação no formulário desta página.'
  ].filter(Boolean).join(' ');

  return { humano, tecnico, temas: temas.map(t => t.emoji), motor: 'interpretador-deterministico' };
}

/* ------------------------------------------------------------------ *
 * 2. Camada opcional de IA generativa                                 *
 * ------------------------------------------------------------------ */
async function interpretarComIA(proj, ctx, base) {
  const key = process.env.OPENAI_API_KEY;
  if (!key) return base;
  const baseUrl = (process.env.OPENAI_BASE_URL || 'https://api.openai.com/v1').replace(/\/$/, '');
  const modelo = process.env.OPENAI_MODEL || 'gpt-4o-mini';
  const prompt = `Você escreve para o portfólio público da IN³ (incubadora da m3d.pro, tecnologia em saúde).
Projeto: ${proj.titulo} | categoria: ${proj.categoria} | status: ${proj.status}
Repositórios: ${proj.repos.join(', ')}
Linguagens: ${Object.keys(ctx.linguagens).join(', ')}
Descrições do GitHub: ${ctx.descricoes || 'nenhuma'}
Commits (mensagens reais): ${ctx.commits.slice(0, 12).map(c => c.msg).join(' | ') || 'nenhum'}
README (trecho): ${limparReadme(ctx.readme).slice(0, 1200) || 'inexistente'}
Responda SOMENTE JSON válido: {"humano":"2 a 3 frases simples, zero jargão, para qualquer pessoa","tecnico":"3 a 4 frases técnicas com stack e estado real"}`;
  try {
    const r = await fetch(`${baseUrl}/chat/completions`, {
      method: 'POST',
      headers: { 'content-type': 'application/json', authorization: `Bearer ${key}` },
      body: JSON.stringify({ model: modelo, temperature: 0.4, response_format: { type: 'json_object' }, messages: [{ role: 'user', content: prompt }] })
    });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const data = await r.json();
    const txt = data.choices?.[0]?.message?.content || '{}';
    const parsed = JSON.parse(txt);
    if (parsed.humano && parsed.tecnico) {
      return { humano: parsed.humano, tecnico: parsed.tecnico, temas: base.temas, motor: `ia:${modelo}` };
    }
    return base;
  } catch (e) {
    console.warn(`  ! IA indisponível para ${proj.id} (${e.message}); usando interpretador determinístico.`);
    return base;
  }
}

/* ------------------------------------------------------------------ *
 * 3. Enriquecimento por projeto                                       *
 * ------------------------------------------------------------------ */
function datasDaSemana() {
  const dias = [];
  const hoje = new Date();
  hoje.setUTCHours(0, 0, 0, 0);
  for (let i = 181; i >= 0; i--) {
    const d = new Date(hoje.getTime() - i * 86400000);
    dias.push(d.toISOString().slice(0, 10));
  }
  return dias;
}

function normalizarCommit(msg) {
  return (msg || '')
    .replace(/^(feat|feature|add|added)\s*[:\-]?\s*/i, 'Adicionado ')
    .replace(/^(fix|bugfix|hotfix)\s*[:\-]?\s*/i, 'Corrigido ')
    .replace(/^(chore|refactor|docs|style|test|perf|build|ci)\s*[:\-]?\s*/i, 'Ajuste técnico: ')
    .replace(/\s+/g, ' ')
    .trim();
}

async function principal() {
  const projetos = [];
  const avisos = [];

  for (const proj of fonte.projetos) {
    const usados = proj.repos.map(n => reposByName.get(n)).filter(Boolean);
    const faltando = proj.repos.filter(n => !reposByName.has(n));
    if (faltando.length) avisos.push(`${proj.id}: sem metadados para ${faltando.join(', ')}`);

    const linguagens = {};
    let tamanhoKb = 0, primeiroPush = null, ultimoPush = null;
    const topicos = new Set();
    const descricoes = [];
    let readme = '';
    const commits = [];

    for (const r of usados) {
      tamanhoKb += kb(r.size);
      if (r.language) linguagens[r.language] = (linguagens[r.language] || 0) + 1;
      (r.topics || []).forEach(t => topicos.add(t));
      if (r.description) descricoes.push(r.description);
      if (r.pushed_at && (!ultimoPush || r.pushed_at > ultimoPush)) ultimoPush = r.pushed_at;
      if (r.created_at && (!primeiroPush || r.created_at < primeiroPush)) primeiroPush = r.created_at;
      if (!readme && readmeCache[r.name]) readme = readmeCache[r.name];
      (commitCache[r.name] || []).forEach(c => commits.push({ ...c, repo: r.name }));
    }
    commits.sort((a, b) => (b.date || '').localeCompare(a.date || ''));

    const ctx = {
      linguagens, tamanhoKb, primeiroPush, ultimoPush,
      topicos: [...topicos], descricoes: descricoes.join(' · '), readme, commits
    };

    const base = interpretarDeterministico(proj, ctx);
    const texto = await interpretarComIA(proj, ctx, base);

    projetos.push({
      id: proj.id,
      titulo: proj.titulo,
      emoji: proj.emoji,
      categoria: proj.categoria,
      status: proj.status,
      mostrar_ao_publico: !!proj.mostrar_ao_publico,
      mostrar_link_repo: !!proj.mostrar_link_repo,
      repos: proj.repos,
      repos_url: proj.mostrar_ao_publico && proj.mostrar_link_repo
        ? proj.repos.map(n => reposByName.get(n)?.html_url).filter(Boolean) : [],
      tech: [...new Set([...Object.keys(linguagens), ...(proj.tech_extra || [])])],
      resumo_publico: texto.humano,
      resumo_tecnico: texto.tecnico,
      emojis_tema: texto.temas,
      motor_interpretacao: texto.motor,
      linha_do_tempo: proj.linha_do_tempo || [],
      imagem_svg: proj.imagem_svg || 'generico',
      playbook: proj.playbook || null,
      metricas: {
        repos: usados.length,
        commits_lidos: commits.length,
        tamanho: fmtKb(tamanhoKb),
        tamanho_kb: tamanhoKb,
        linguagens: Object.keys(linguagens),
        primeiro_registro: primeiroPush ? primeiroPush.slice(0, 10) : null,
        ultimo_registro: ultimoPush ? ultimoPush.slice(0, 10) : null
      },
      commits: commits.slice(0, 40)
    });
  }

  /* -------- feed de notícias (linguagem humana + original técnico) -------- */
  const noticias = [];
  for (const pr of projetos) {
    for (const c of pr.commits.slice(0, 6)) {
      noticias.push({
        data: (c.date || '').slice(0, 10),
        projeto_id: pr.id,
        projeto: pr.titulo,
        emoji: pr.emoji,
        tipo: 'commit',
        humano: `${normalizarCommit(c.msg) || 'Avanço registrado'} — em ${pr.titulo.replace(/\s+—.*/, '')}.`,
        tecnico: `${c.repo} · ${c.sha} · ${c.msg}`
      });
    }
    for (const m of pr.linha_do_tempo) {
      noticias.push({
        data: m.data, projeto_id: pr.id, projeto: pr.titulo, emoji: pr.emoji,
        tipo: 'marco', humano: m.marco + '.', tecnico: `${pr.repos.join(' · ')} — marco de projeto`
      });
    }
    if (pr.status === 'ativo' || pr.status === 'em incubação') {
      noticias.push({
        data: pr.metricas.ultimo_registro || fonte.gerado_em,
        projeto_id: pr.id, projeto: pr.titulo, emoji: pr.emoji, tipo: 'status',
        humano: `${pr.titulo.replace(/\s+—.*/, '')} segue em desenvolvimento dentro da IN³.`,
        tecnico: `status=${pr.status}; repos=${pr.repos.join(', ')}`
      });
    }
  }
  noticias.sort((a, b) => (b.data || '').localeCompare(a.data || ''));
  const feed = noticias.slice(0, 24);

  /* -------- mapa de calor / calendário de interações -------- */
  const porDia = {};
  for (const pr of projetos) for (const c of pr.commits) {
    const d = (c.date || '').slice(0, 10);
    if (d) porDia[d] = (porDia[d] || 0) + 1;
  }
  const heatmap = datasDaSemana().map(d => ({ data: d, total: porDia[d] || 0 }));

  /* -------- badges de tecnologia -------- */
  const techCount = {};
  for (const pr of projetos) for (const t of pr.tech) techCount[t] = (techCount[t] || 0) + 1;
  const tecnologias = Object.entries(techCount)
    .map(([nome, total]) => ({ nome, total }))
    .sort((a, b) => b.total - a.total);

  /* -------- calendário de interações por projeto/mês -------- */
  const meses = [...new Set(noticias.map(n => (n.data || '').slice(0, 7)).filter(Boolean))].sort().slice(-8);
  const matriz = meses.map(m => ({
    mes: m,
    itens: projetos.filter(pr => pr.commits.some(c => (c.date || '').startsWith(m))).map(pr => pr.id)
  }));

  const publicos = projetos.filter(x => x.mostrar_ao_publico);
  const privados = projetos.filter(x => !x.mostrar_ao_publico);

  const payload = {
    meta: {
      gerado_em: new Date().toISOString(),
      versao: '1.0.0',
      host_publico: fonte.brand.host_publico,
      fonte_dados: 'API pública do GitHub (coleta server-side) — nenhum dado bruto é exposto na página',
      itens_publicos: publicos.length,
      itens_privados: privados.length,
      avisos
    },
    brand: fonte.brand,
    github: fonte.github,
    politica_acesso: fonte.politica_acesso,
    estatisticas: {
      projetos: publicos.length,
      repositorios: publicos.reduce((a, b) => a + b.metricas.repos, 0),
      commits: publicos.reduce((a, b) => a + b.metricas.commits_lidos, 0),
      linguagens: new Set(publicos.flatMap(x => x.metricas.linguagens)).size,
      categorias: [...new Set(publicos.map(x => x.categoria))].length,
      atualizado: fonte.gerado_em
    },
    projetos: publicos,
    noticias: feed.filter(n => publicos.some(pr => pr.id === n.projeto_id)),
    heatmap,
    tecnologias,
    calendario: matriz
  };

  /* -------- render dos templates -------- */
  const alvoPublico = JSON.stringify(payload, null, 0);
  const htmlIndex = fs.readFileSync(p('template/index.template.html'), 'utf8')
    .split('/*__DATA__*/ null').join(alvoPublico);
  fs.writeFileSync(p('index.html'), htmlIndex);

  const htmlAdmin = fs.readFileSync(p('template/admin.template.html'), 'utf8')
    .split('/*__DATA__*/ null').join(JSON.stringify({
      brand: fonte.brand,
      categorias: [...new Set(fonte.projetos.map(x => x.categoria))],
      status_opcoes: ['ativo', 'em incubação', 'protótipo', 'pesquisa', 'entregue', 'backlog', 'arquivado', 'infra'],
      github_conta: fonte.github.conta,
      gerado_em: fonte.gerado_em
    }));
  fs.writeFileSync(p('admin.html'), htmlAdmin);

  fs.mkdirSync(p('data'), { recursive: true });
  fs.writeFileSync(p('data/portfolio.json'), JSON.stringify(payload, null, 2));
  fs.writeFileSync(p('data/portfolio.full.json'), JSON.stringify({
    meta: { gerado_em: new Date().toISOString(), contem_itens_privados: true, aviso: 'ARQUIVO INTERNO — não publique em hospedagem estática.' },
    brand: fonte.brand, github: fonte.github, projetos
  }, null, 2));

  console.log('✔ build concluído');
  console.log(`  itens públicos : ${publicos.length}`);
  console.log(`  itens privados : ${privados.length} (não injetados em index.html)`);
  console.log(`  notícias       : ${payload.noticias.length}`);
  console.log(`  commits lidos  : ${payload.estatisticas.commits}`);
  console.log(`  motores        : ${[...new Set(projetos.map(x => x.motor_interpretacao))].join(', ')}`);
  if (avisos.length) console.log('  avisos: ' + avisos.join(' | '));
  for (const pr of publicos) console.log(`  · ${pr.emoji} ${pr.titulo} → ${pr.resumo_publico.slice(0, 90)}...`);
}

principal();

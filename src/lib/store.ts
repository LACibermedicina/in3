// Camada de persistencia simples (arquivo JSON). Node runtime apenas.
import fs from 'node:fs';
import path from 'node:path';

const FILE = path.join(process.cwd(), 'data', 'store.json');

export type Project = {
  id: string;
  slug: string;
  name: string;
  repo: string | null;
  visibility: 'public' | 'private';
  status: 'initial' | 'building' | 'done';
  progress: number;      // 0..100 (gerado pela curadoria de IA)
  roi: number;           // % estimado
  heat: 'warm' | 'cool';
  tokensRaised: number;
  seed: number;          // usado para gerar a arvore unica
  x: number;
  z: number;
  summary: string;
  released: boolean;     // filosofia "nada nasce publico"
  createdAt: string;
};

export type AccessToken = {
  id: string;
  label: string;
  hash: string;          // scrypt$salt$hash
  role: 'admin' | 'guest';
  active: boolean;
  createdAt: string;
  expiresAt: string | null;   // null = sem prazo
  issuedBy: string;
  revokedAt: string | null;
  note?: string;
};

export type AccessLog = {
  id: string;
  tokenId: string;
  label: string;
  role: 'admin' | 'guest';
  path: string;
  kind: 'page' | 'api';
  projectSlug: string | null;
  ts: string;
  ms: number;             // duracao do acesso (proxy de engajamento)
};

type DB = {
  projects: Project[];
  tokens: AccessToken[];
  logs: AccessLog[];
  settings: { onboardingComplete: boolean; repoUrl: string | null; aiProvider: string | null };
};

const EMPTY: DB = { projects: [], tokens: [], logs: [], settings: { onboardingComplete: false, repoUrl: null, aiProvider: null } };

let cache: DB | null = null;

export function db(): DB {
  if (cache) return cache;
  try {
    if (fs.existsSync(FILE)) {
      cache = { ...EMPTY, ...JSON.parse(fs.readFileSync(FILE, 'utf8')) } as DB;
    } else {
      cache = structuredClone(EMPTY);
    }
  } catch {
    cache = structuredClone(EMPTY);
  }
  if (!cache.projects.length) seed(cache);
  return cache;
}

export function persist(): void {
  if (!cache) return;
  fs.mkdirSync(path.dirname(FILE), { recursive: true });
  fs.writeFileSync(FILE, JSON.stringify(cache, null, 2));
}

export function uid(prefix: string): string {
  return `${prefix}_${Math.random().toString(36).slice(2, 10)}${Date.now().toString(36).slice(-4)}`;
}

export function slugify(v: string): string {
  return v.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 48) || 'projeto';
}

/* ---------------------------------------------------------------------------
   Seed de demonstracao: projetos incubados com coordenadas isometricas,
   paletas quentes/frias e percentuais de desenvolvimento.
   Substitua pelo resultado real da curadoria automatica de IA.
--------------------------------------------------------------------------- */
function seed(d: DB): void {
  const raw: Array<[string, string, Project['status'], number, number, Project['heat'], number]> = [
    ['Bosque Aurora', 'incubadora/bosque-aurora', 'done', 100, 24.8, 'warm', 18200],
    ['Clareira Nexus', 'incubadora/clareira-nexus', 'building', 72, 15.2, 'warm', 12400],
    ['Raiz Estavel', 'incubadora/raiz-estavel', 'done', 100, 9.4, 'cool', 9800],
    ['Semente Pulsar', 'incubadora/semente-pulsar', 'building', 48, 31.5, 'warm', 7600],
    ['Musgo Lento', 'incubadora/musgo-lento', 'done', 100, 7.1, 'cool', 6100],
    ['Copa Coral', 'incubadora/copa-coral', 'building', 61, 19.3, 'warm', 5400],
    ['Neblina Quanta', 'incubadora/neblina-quanta', 'initial', 22, 42.0, 'cool', 3100],
    ['Tronco Farol', 'incubadora/tronco-farol', 'done', 100, 12.6, 'cool', 4700],
    ['Broto Vertice', 'incubadora/broto-vertice', 'initial', 34, 27.9, 'warm', 2600],
    ['Cascata Zeta', 'incubadora/cascata-zeta', 'building', 85, 21.4, 'cool', 8900],
    ['Penhasco Orion', 'incubadora/penhasco-orion', 'done', 100, 18.2, 'warm', 7300],
    ['Ilha Vaga-Lume', 'incubadora/ilha-vaga-lume', 'initial', 15, 35.7, 'warm', 1500]
  ];
  raw.forEach((r, i) => {
    const [name, repo, status, progress, roi, heat, tokens] = r;
    const angle = (i / raw.length) * Math.PI * 2;
    const radius = 6.5 + (i % 3) * 2.4 + (i % 2) * 1.1;
    d.projects.push({
      id: uid('prj'),
      slug: slugify(name),
      name,
      repo,
      visibility: i % 4 === 0 ? 'private' : 'public',
      status, progress, roi, heat,
      tokensRaised: tokens,
      seed: 1000 + i * 977,
      x: +(Math.cos(angle) * radius).toFixed(3),
      z: +(Math.sin(angle) * radius).toFixed(3),
      summary:
        status === 'done'
          ? 'Casa na arvore concluida. Recursos em uso, rodando em producao.'
          : status === 'building'
          ? 'Casa na arvore em construcao com andaimes de madeira.'
          : 'Terreno preparado, sementes plantadas. Curadoria inicial em curso.',
      released: status !== 'initial' || i % 5 === 0,
      createdAt: new Date(Date.now() - (i + 1) * 864e5).toISOString()
    });
  });
}

// Agregacoes sobre o log de navegacao por token -> dashboards e relatorios.
import type { AccessLog, Project } from './store';

export type Bucket = { key: string; label: string; hits: number; ms: number };

function tally(logs: AccessLog[], keyOf: (l: AccessLog) => string | null): Bucket[] {
  const map = new Map<string, { hits: number; ms: number }>();
  for (const l of logs) {
    const k = keyOf(l);
    if (!k) continue;
    const cur = map.get(k) ?? { hits: 0, ms: 0 };
    cur.hits += 1;
    cur.ms += l.ms || 0;
    map.set(k, cur);
  }
  return [...map.entries()]
    .map(([key, v]) => ({ key, label: key, hits: v.hits, ms: v.ms }))
    .sort((a, b) => b.hits - a.hits);
}

export function buildReport(logs: AccessLog[], projects: Project[], windowDays = 14) {
  const since = Date.now() - windowDays * 864e5;
  const scoped = logs.filter((l) => new Date(l.ts).getTime() >= since);

  const byDay = new Map<string, number>();
  for (let i = windowDays - 1; i >= 0; i--) {
    const d = new Date(Date.now() - i * 864e5);
    byDay.set(d.toISOString().slice(0, 10), 0);
  }
  for (const l of scoped) {
    const k = l.ts.slice(0, 10);
    if (byDay.has(k)) byDay.set(k, (byDay.get(k) ?? 0) + 1);
  }

  const nameBySlug = new Map(projects.map((p) => [p.slug, p.name]));
  const projectHits = tally(scoped, (l) => l.projectSlug).map((b) => ({
    ...b,
    label: nameBySlug.get(b.key) ?? b.key
  }));

  return {
    windowDays,
    total: scoped.length,
    uniqueTokens: new Set(scoped.map((l) => l.tokenId)).size,
    byDay: [...byDay.entries()].map(([date, hits]) => ({ date: date.slice(5), hits })),
    byProject: projectHits.slice(0, 12),
    byPath: tally(scoped, (l) => (l.projectSlug ? null : l.path)).slice(0, 10),
    byToken: tally(scoped, (l) => `${l.label} (${l.role})`).slice(0, 10),
    heat: projectHits.slice(0, 5),
    generatedAt: new Date().toISOString()
  };
}

export type Report = ReturnType<typeof buildReport>;

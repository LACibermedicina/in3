import { NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { buildReport } from '@/lib/analytics';
import { db } from '@/lib/store';
import { COOKIE_NAME, verifySession } from '@/lib/session';

export const runtime = 'nodejs';

export async function GET(req: Request) {
  const session = await verifySession(cookies().get(COOKIE_NAME)?.value, process.env.SESSION_SECRET ?? '');
  if (!session || session.role !== 'admin') {
    return NextResponse.json({ ok: false, error: 'apenas admin' }, { status: 403 });
  }

  const days = Number(new URL(req.url).searchParams.get('days') ?? 14);
  const store = db();

  // Enriquecimento opcional: injeta um ruido de fundo estavel apenas para demonstracao
  // quando o log ainda esta vazio. Remova em producao.
  const logs = store.logs.length
    ? store.logs
    : demoLogs(store.projects.map((p) => p.slug), store.projects.map((p) => p.name));

  return NextResponse.json({ ok: true, report: buildReport(logs, store.projects, days), seeded: store.logs.length === 0 });
}

function demoLogs(slugs: string[], _names: string[]) {
  const out = [] as any[];
  const labels = ['Convidado', 'Parceiro', 'Banca', 'Administrador'];
  const paths = ['/map', '/dashboard', '/', '/admin'];
  for (let d = 13; d >= 0; d--) {
    const perDay = 8 + ((d * 7) % 17);
    for (let i = 0; i < perDay; i++) {
      const slug = slugs[(d * 3 + i * 5) % slugs.length];
      const isProject = i % 3 !== 0;
      out.push({
        id: `demo_${d}_${i}`,
        tokenId: `t${i % labels.length}`,
        label: labels[i % labels.length],
        role: i % labels.length === 3 ? 'admin' : 'guest',
        path: isProject ? `/p/${slug}` : paths[i % paths.length],
        kind: 'page',
        projectSlug: isProject ? slug : null,
        ts: new Date(Date.now() - d * 864e5 - i * 6e5).toISOString(),
        ms: 1800 + ((i * 977) % 9000)
      });
    }
  }
  return out;
}

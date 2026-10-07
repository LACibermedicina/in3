import { NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { db, persist, uid, type AccessLog } from '@/lib/store';
import { COOKIE_NAME, verifySession } from '@/lib/session';

export const runtime = 'nodejs';

export async function POST(req: Request) {
  const session = await verifySession(cookies().get(COOKIE_NAME)?.value, process.env.SESSION_SECRET ?? '');
  if (!session) return NextResponse.json({ ok: false }, { status: 401 });

  const body = await req.json().catch(() => ({}));
  const store = db();

  const entry: AccessLog = {
    id: uid('log'),
    tokenId: session.sub,
    label: session.label,
    role: session.role,
    path: String(body?.path ?? '/').slice(0, 200),
    kind: body?.kind === 'api' ? 'api' : 'page',
    projectSlug: body?.projectSlug ? String(body.projectSlug).slice(0, 80) : null,
    ts: new Date().toISOString(),
    ms: Number.isFinite(body?.ms) ? Math.max(0, Math.min(3_600_000, Number(body.ms))) : 0
  };

  store.logs.push(entry);
  if (store.logs.length > 20000) store.logs.splice(0, store.logs.length - 20000); // retencao
  persist();

  return NextResponse.json({ ok: true });
}

export async function GET() {
  const session = await verifySession(cookies().get(COOKIE_NAME)?.value, process.env.SESSION_SECRET ?? '');
  if (!session || session.role !== 'admin') {
    return NextResponse.json({ ok: false, error: 'apenas admin' }, { status: 403 });
  }
  const store = db();
  return NextResponse.json({ ok: true, logs: [...store.logs].reverse().slice(0, 300) });
}

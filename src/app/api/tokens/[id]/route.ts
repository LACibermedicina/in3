import { NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { tokenState } from '@/lib/auth';
import { db, persist } from '@/lib/store';
import { COOKIE_NAME, verifySession } from '@/lib/session';

export const runtime = 'nodejs';

async function requireAdmin() {
  const s = await verifySession(cookies().get(COOKIE_NAME)?.value, process.env.SESSION_SECRET ?? '');
  return s && s.role === 'admin' ? s : null;
}

type Ctx = { params: { id: string } };

/** PATCH: ativar, desativar, reprogramar prazo ou revogar. */
export async function PATCH(req: Request, { params }: Ctx) {
  if (!(await requireAdmin())) return NextResponse.json({ ok: false, error: 'apenas admin' }, { status: 403 });

  const store = db();
  const t = store.tokens.find((x) => x.id === params.id);
  if (!t) return NextResponse.json({ ok: false, error: 'nao encontrado' }, { status: 404 });

  const body = await req.json().catch(() => ({}));

  if (typeof body?.active === 'boolean') {
    t.active = body.active;
    if (body.active) t.revokedAt = null;      // reativar limpa a revogacao
  }
  if (body?.expiresInHours !== undefined) {
    const h = Number(body.expiresInHours);
    t.expiresAt = h > 0 ? new Date(Date.now() + h * 3600 * 1000).toISOString() : null;
  }
  if (body?.expiresAt !== undefined) {
    t.expiresAt = body.expiresAt ? new Date(body.expiresAt).toISOString() : null;
  }
  if (typeof body?.label === 'string' && body.label.trim()) t.label = body.label.trim().slice(0, 60);
  if (body?.revoke === true) {
    t.revokedAt = new Date().toISOString();
    t.active = false;
  }

  persist();
  return NextResponse.json({ ok: true, state: tokenState(t), token: { ...t, hash: undefined } });
}

/** DELETE: remove o registro e mantem o historico (o log guarda o rotulo). */
export async function DELETE(_req: Request, { params }: Ctx) {
  if (!(await requireAdmin())) return NextResponse.json({ ok: false, error: 'apenas admin' }, { status: 403 });
  const store = db();
  const i = store.tokens.findIndex((x) => x.id === params.id);
  if (i < 0) return NextResponse.json({ ok: false, error: 'nao encontrado' }, { status: 404 });
  store.tokens.splice(i, 1);
  persist();
  return NextResponse.json({ ok: true });
}

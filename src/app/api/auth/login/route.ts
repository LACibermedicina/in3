import { NextResponse } from 'next/server';
import { resolveToken } from '@/lib/auth';
import { db, persist, uid } from '@/lib/store';
import { COOKIE_NAME, signSession, type SessionPayload } from '@/lib/session';

export const runtime = 'nodejs';

const SESSION_HOURS = 8;

export async function POST(req: Request) {
  const body = await req.json().catch(() => ({}));
  const presented = String(body?.token ?? '').trim();

  if (!presented) {
    return NextResponse.json({ ok: false, error: 'Informe o token de acesso.' }, { status: 400 });
  }

  const resolved = resolveToken(presented);

  // Log de tentativa (sucesso e falha) para auditoria.
  const store = db();
  store.logs.push({
    id: uid('log'),
    tokenId: resolved.id || 'desconhecido',
    label: resolved.label || 'tentativa invalida',
    role: resolved.role,
    path: '/entrar',
    kind: 'api',
    projectSlug: null,
    ts: new Date().toISOString(),
    ms: resolved.ok ? 1 : 0,
    ...(resolved.ok ? {} : { path: `/entrar?falha=${encodeURIComponent(resolved.reason ?? 'erro')}` })
  } as any);
  persist();

  if (!resolved.ok) {
    return NextResponse.json({ ok: false, error: `Acesso negado: ${resolved.reason}.` }, { status: 401 });
  }

  const now = Date.now();
  const payload: SessionPayload = {
    sub: resolved.id,
    label: resolved.label,
    role: resolved.role,
    iat: now,
    exp: now + SESSION_HOURS * 3600 * 1000,
    sid: uid('sid')
  };

  const secret = process.env.SESSION_SECRET ?? '';
  if (!secret) {
    return NextResponse.json(
      { ok: false, error: 'SESSION_SECRET nao configurado no servidor. Veja .env.example.' },
      { status: 500 }
    );
  }

  const token = await signSession(payload, secret);
  const res = NextResponse.json({ ok: true, role: resolved.role, label: resolved.label });
  res.cookies.set(COOKIE_NAME, token, {
    httpOnly: true,
    sameSite: 'lax',
    secure: process.env.NODE_ENV === 'production',
    path: '/',
    maxAge: SESSION_HOURS * 3600
  });
  return res;
}

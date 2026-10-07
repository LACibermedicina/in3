import { NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { randomBytes } from 'node:crypto';
import { hashToken, tokenState } from '@/lib/auth';
import { db, persist, uid, type AccessToken } from '@/lib/store';
import { COOKIE_NAME, verifySession } from '@/lib/session';

export const runtime = 'nodejs';

async function requireAdmin() {
  const s = await verifySession(cookies().get(COOKIE_NAME)?.value, process.env.SESSION_SECRET ?? '');
  return s && s.role === 'admin' ? s : null;
}

export async function GET() {
  if (!(await requireAdmin())) return NextResponse.json({ ok: false, error: 'apenas admin' }, { status: 403 });
  const store = db();
  return NextResponse.json({
    ok: true,
    tokens: store.tokens.map((t) => ({
      id: t.id,
      label: t.label,
      role: t.role,
      state: tokenState(t),
      active: t.active,
      createdAt: t.createdAt,
      expiresAt: t.expiresAt,
      revokedAt: t.revokedAt,
      note: t.note ?? null,
      hits: store.logs.filter((l) => l.tokenId === t.id).length
    }))
  });
}

export async function POST(req: Request) {
  const admin = await requireAdmin();
  if (!admin) return NextResponse.json({ ok: false, error: 'apenas admin' }, { status: 403 });

  const body = await req.json().catch(() => ({}));
  const label = String(body?.label ?? '').trim() || 'Convidado';
  const hours = Number(body?.hours ?? 0);           // 0 = sem prazo definido
  const role: AccessToken['role'] = body?.role === 'admin' ? 'admin' : 'guest';

  // Token aleatorio de 32 bytes. Nao existe token "adivinhavel".
  const plain = `in3_${randomBytes(24).toString('base64url')}`;

  const record: AccessToken = {
    id: uid('tok'),
    label,
    hash: hashToken(plain),
    role,
    active: true,
    createdAt: new Date().toISOString(),
    expiresAt: hours > 0 ? new Date(Date.now() + hours * 3600 * 1000).toISOString() : null,
    issuedBy: admin.label,
    revokedAt: null,
    note: body?.note ? String(body.note).slice(0, 200) : undefined
  };

  const store = db();
  store.tokens.push(record);
  persist();

  // O valor em texto puro aparece UMA vez, na resposta. Nunca e persistido.
  return NextResponse.json({ ok: true, token: { ...record, hash: undefined }, plain });
}

import { NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { db } from '@/lib/store';
import { COOKIE_NAME, verifySession } from '@/lib/session';

export const runtime = 'nodejs';

export async function GET() {
  const session = await verifySession(cookies().get(COOKIE_NAME)?.value, process.env.SESSION_SECRET ?? '');
  if (!session) return NextResponse.json({ ok: false }, { status: 401 });

  const store = db();
  // Filosofia "Nada Nasce Publico": convidado ve apenas itens liberados.
  const visible = session.role === 'admin' ? store.projects : store.projects.filter((p) => p.released);

  return NextResponse.json({
    ok: true,
    viewer: { label: session.label, role: session.role },
    projects: visible
  });
}

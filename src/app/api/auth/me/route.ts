import { NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { COOKIE_NAME, verifySession } from '@/lib/session';

export const runtime = 'nodejs';

export async function GET() {
  const session = await verifySession(cookies().get(COOKIE_NAME)?.value, process.env.SESSION_SECRET ?? '');
  if (!session) return NextResponse.json({ ok: false }, { status: 401 });
  return NextResponse.json({
    ok: true,
    label: session.label,
    role: session.role,
    expiresAt: new Date(session.exp).toISOString()
  });
}

import { NextResponse, type NextRequest } from 'next/server';
import { COOKIE_NAME, verifySession } from './src/lib/session';

const PROTECTED = ['/map', '/admin', '/dashboard', '/p'];
const PUBLIC_FILES = /\.(png|jpg|jpeg|svg|webp|ico|css|js|map|woff2?)$/i;

export async function middleware(req: NextRequest) {
  const { pathname, origin } = req.nextUrl;

  const isProtected = PROTECTED.some((p) => pathname === p || pathname.startsWith(`${p}/`));
  if (!isProtected || PUBLIC_FILES.test(pathname)) return NextResponse.next();

  const secret = process.env.SESSION_SECRET ?? '';
  const session = await verifySession(req.cookies.get(COOKIE_NAME)?.value, secret);

  if (!session) {
    const url = req.nextUrl.clone();
    url.pathname = '/';
    url.searchParams.set('next', pathname);
    url.searchParams.set('err', 'sessao-invalida');
    return NextResponse.redirect(url);
  }

  // Registro de navegacao por token. Sem bloquear a resposta.
  const projectSlug = pathname.startsWith('/p/') ? pathname.split('/')[2] ?? null : null;
  void fetch(`${origin}/api/log`, {
    method: 'POST',
    headers: { 'content-type': 'application/json', cookie: req.headers.get('cookie') ?? '' },
    body: JSON.stringify({ path: pathname, kind: 'page', projectSlug, ms: 0 }),
    keepalive: true
  }).catch(() => undefined);

  const res = NextResponse.next();
  res.headers.set('x-in3-token', session.label);
  res.headers.set('x-in3-role', session.role);
  return res;
}

export const config = {
  matcher: ['/map/:path*', '/admin/:path*', '/dashboard/:path*', '/p/:path*']
};

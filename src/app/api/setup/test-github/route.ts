import { NextResponse } from 'next/server';
import { db, persist } from '@/lib/store';

export const runtime = 'nodejs';

export async function POST(req: Request) {
  const body = await req.json().catch(() => ({}));
  const url = String(body?.repoUrl ?? '').trim();
  const pat = String(body?.pat ?? '').trim();

  const m = url.match(/github\.com[/:]([^/]+)\/([^/.]+)/i);
  if (!m) return NextResponse.json({ ok: false, error: 'Informe uma URL valida de repositorio GitHub.' }, { status: 400 });

  const [, owner, repo] = m;
  const headers: Record<string, string> = { accept: 'application/vnd.github+json', 'user-agent': 'in3-cube3' };
  if (pat) headers.authorization = `Bearer ${pat}`;

  try {
    const res = await fetch(`https://api.github.com/repos/${owner}/${repo}`, { headers, cache: 'no-store' });
    if (res.status === 404) {
      return NextResponse.json({
        ok: false,
        private: true,
        error: pat
          ? 'Repositorio nao encontrado com esse token (PAT). Verifique o escopo "repo".'
          : 'Repositorio privado ou inexistente. Um token do GitHub (PAT) e necessario.'
      }, { status: 404 });
    }
    if (!res.ok) {
      return NextResponse.json({ ok: false, error: `GitHub respondeu ${res.status}.` }, { status: 502 });
    }
    const data = await res.json();

    const store = db();
    store.settings.repoUrl = url;

    // A IA curada decide o que pode ir ao ar. Aqui apenas registramos o candidato.
    const slug = String(repo).toLowerCase().replace(/[^a-z0-9]+/g, '-');
    if (!store.projects.some((p) => p.slug === slug)) {
      const n = store.projects.length;
      const angle = (n / 12) * Math.PI * 2;
      store.projects.push({
        id: `prj_${slug}`,
        slug,
        name: String(data.name ?? repo),
        repo: `${owner}/${repo}`,
        visibility: data.private ? 'private' : 'public',
        status: 'initial',
        progress: 8,
        roi: 0,
        heat: n % 2 ? 'cool' : 'warm',
        tokensRaised: 0,
        seed: 4000 + n * 613,
        x: +(Math.cos(angle) * (7 + (n % 3) * 2.2)).toFixed(3),
        z: +(Math.sin(angle) * (7 + (n % 3) * 2.2)).toFixed(3),
        summary: 'Importado pelo setup autonomo. Aguardando curadoria de IA ("nada nasce publico").',
        released: false,
        createdAt: new Date().toISOString()
      });
    }
    persist();

    return NextResponse.json({
      ok: true,
      repo: {
        name: data.name, fullName: data.full_name, private: data.private,
        language: data.language, stars: data.stargazers_count, forks: data.forks_count,
        openIssues: data.open_issues_count, defaultBranch: data.default_branch,
        pushedAt: data.pushed_at, description: data.description
      }
    });
  } catch {
    return NextResponse.json({ ok: false, error: 'Falha de rede ao consultar a API do GitHub.' }, { status: 502 });
  }
}

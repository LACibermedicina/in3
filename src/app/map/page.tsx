'use client';

import dynamic from 'next/dynamic';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Hud from '@/components/Hud';
import MiniMap from '@/components/MiniMap';
import ProjectDrawer from '@/components/ProjectDrawer';
import type { ProjectLite } from '@/components/ForestCanvas';

const ForestCanvas = dynamic(() => import('@/components/ForestCanvas'), {
  ssr: false,
  loading: () => <div className="grid h-full place-items-center text-slate-400">carregando floresta…</div>
});

export default function MapPage() {
  const router = useRouter();
  const [projects, setProjects] = useState<ProjectLite[]>([]);
  const [me, setMe] = useState<{ label: string; role: string } | null>(null);
  const [active, setActive] = useState<ProjectLite | null>(null);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      const res = await fetch('/api/projects');
      if (!res.ok) { setErr('Sessao ausente ou expirada.'); return; }
      const data = await res.json();
      setProjects(data.projects);
      setMe(data.viewer);

      // Registro de permanencia na floresta (alimenta os dashboards).
      const t0 = Date.now();
      const beat = () => fetch('/api/log', {
        method: 'POST', headers: { 'content-type': 'application/json' },
        body: JSON.stringify({ path: '/map', kind: 'page', projectSlug: null, ms: Date.now() - t0 })
      }).catch(() => undefined);
      window.addEventListener('beforeunload', beat);
      const iv = setInterval(beat, 30000);
      return () => { clearInterval(iv); window.removeEventListener('beforeunload', beat); };
    })();
  }, []);

  const onSelect = useCallback((p: ProjectLite) => {
    setActive(p);
    fetch('/api/log', {
      method: 'POST', headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ path: `/p/${p.slug}`, kind: 'api', projectSlug: p.slug, ms: 0 })
    }).catch(() => undefined);
  }, []);

  async function logout() {
    await fetch('/api/auth/logout', { method: 'POST' });
    router.push('/');
  }

  const wallet = projects.reduce((a, p) => a + p.tokensRaised, 0);

  return (
    <div className="relative h-screen w-screen overflow-hidden">
      <div className="pointer-events-none absolute inset-0 z-10">
        <div className="pointer-events-none absolute left-1/2 top-3 z-10 -translate-x-1/2">
          <Hud
            label={me?.label ?? '—'} role={me?.role ?? 'guest'} onLogout={logout}
            tokens={wallet} plots={projects.filter((p) => p.released).length}
          />
        </div>
      </div>

      {err ? (
        <div className="grid h-full place-items-center">
          <div className="panel p-6 text-center">
            <p className="mb-3 text-rose-200">{err}</p>
            <a className="rounded-xl bg-amber-400 px-4 py-2 font-bold text-slate-900" href="/">voltar ao acesso</a>
          </div>
        </div>
      ) : (
        <div className="h-full w-full">
          <ForestCanvas projects={projects} onSelect={onSelect} />
        </div>
      )}

      <div className="absolute bottom-4 left-4 z-20 w-64">
        <MiniMap
          projects={projects}
          active={active?.slug}
          onPick={(slug) => setActive(projects.find((p) => p.slug === slug) ?? null)}
        />
        <div className="panel-soft mt-2 p-3 text-[10px] text-slate-400">
          arraste para orbitar · scroll para aproximar · clique na árvore para abrir o painel
        </div>
      </div>

      <ProjectDrawer p={active} onClose={() => setActive(null)} />
    </div>
  );
}

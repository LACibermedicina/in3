'use client';

import { useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';

type Node = { href: string; title: string; desc: string; icon: string; tone: 'gold' | 'cool' | 'grass' };

const INDEX: Node[] = [
  { href: '/map', title: 'Floresta de Projetos', desc: 'Mapa isometrico 3D. Cada arvore e um projeto incubado.', icon: '🌳', tone: 'grass' },
  { href: '/dashboard', title: 'Analise de Acesso', desc: 'Dashboards e relatorios visuais de interesse por item e projeto.', icon: '📊', tone: 'cool' },
  { href: '/admin', title: 'Tokens de Acesso', desc: 'Gerar, ativar, desativar e programar a validade dos acessos.', icon: '🔑', tone: 'gold' },
  { href: '/setup', title: 'Setup Autonomo', desc: 'Conectar repositorio GitHub e provedores de IA.', icon: '⚙️', tone: 'cool' }
];

export default function Home() {
  const router = useRouter();
  const params = useSearchParams();
  const nextPath = params.get('next') || '/map';
  const [token, setToken] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(params.get('err'));

  async function enter(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setErr(null);
    try {
      const res = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({ token })
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        setErr(data.error || 'Nao foi possivel validar o token.');
        return;
      }
      router.push(nextPath);
    } catch {
      setErr('Falha de rede ao validar o token.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="mx-auto max-w-5xl px-6 py-14">
      <header className="rise mb-12 text-center">
        <div className="mb-3 inline-flex items-center gap-3 rounded-full border border-amber-300/30 bg-amber-300/10 px-4 py-1 text-xs tracking-widest text-amber-200">
          🧊 IN3 · CUBE 3 · m3d.pro
        </div>
        <h1 className="text-5xl font-black tracking-tight md:text-6xl">
          Estufa de Inovação <span className="text-amber-300">IN³</span>
        </h1>
        <p className="mx-auto mt-4 max-w-2xl text-lg text-slate-300">
          Incubadora gamificada em floresta isométrica low-poly: casas na árvore em construção,
          cachoeiras, nuvens e tokens de acesso que abrem o mapa.
        </p>
      </header>

      {/* ---------------- Entrada por token ---------------- */}
      <section className="panel rise mb-12 p-7">
        <h2 className="mb-1 flex items-center gap-2 text-xl font-bold">🔐 Entrar com token de acesso</h2>
        <p className="mb-5 text-sm text-slate-400">
          O acesso é concedido por token. Tokens temporários são gerados dentro do sistema por um
          administrador e podem ser ativados, desativados ou expirados a qualquer momento.
        </p>

        <form onSubmit={enter} className="flex flex-col gap-3 sm:flex-row">
          <input
            type="password"
            value={token}
            onChange={(e) => setToken(e.target.value)}
            placeholder="Cole seu token de acesso"
            autoComplete="off"
            className="flex-1 rounded-xl border border-slate-600/50 bg-slate-900/60 px-4 py-3 text-slate-100 placeholder:text-slate-500"
          />
          <button
            type="submit"
            disabled={busy || !token}
            className="glow-gold rounded-xl bg-amber-400 px-7 py-3 font-bold text-slate-900 transition hover:bg-amber-300 disabled:opacity-40"
          >
            {busy ? 'Validando…' : 'Entrar'}
          </button>
        </form>

        {err && (
          <p className="mt-4 rounded-lg border border-rose-400/30 bg-rose-500/10 px-4 py-2 text-sm text-rose-200">
            ⚠️ {err}
          </p>
        )}

        <p className="mt-4 text-xs text-slate-500">
          A sessão é assinada no servidor e expira em 8 horas. Toda navegação é registrada no
          histórico do token que a realizou.
        </p>
      </section>

      {/* ---------------- Índice do sistema ---------------- */}
      <section className="rise">
        <h2 className="mb-5 text-xl font-bold">🗺️ Índice do sistema</h2>
        <div className="grid gap-4 sm:grid-cols-2">
          {INDEX.map((n) => (
            <a
              key={n.href}
              href={n.href}
              className={`panel-soft group block p-5 transition hover:-translate-y-0.5 ${
                n.tone === 'gold' ? 'glow-gold' : n.tone === 'cool' ? 'glow-cool' : ''
              }`}
            >
              <div className="mb-2 text-2xl">{n.icon}</div>
              <div className="font-bold text-slate-100 group-hover:text-amber-200">{n.title}</div>
              <div className="mt-1 text-sm text-slate-400">{n.desc}</div>
              <div className="mt-3 text-xs font-mono text-slate-500">{n.href}</div>
            </a>
          ))}
        </div>
      </section>

      <footer className="mt-14 border-t border-slate-700/40 pt-6 text-center text-xs text-slate-500">
        Filosofia <strong className="text-slate-400">“Nada Nasce Público”</strong> — itens em curadoria
        inicial não são publicados sem liberação expressa. IN³ · m3d.pro
      </footer>
    </main>
  );
}

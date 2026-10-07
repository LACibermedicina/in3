'use client';

import { useEffect, useState } from 'react';
import {
  BarChart, Bar, LineChart, Line, PieChart, Pie, Cell,
  XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend
} from 'recharts';

type Report = {
  windowDays: number; total: number; uniqueTokens: number;
  byDay: { date: string; hits: number }[];
  byProject: { key: string; label: string; hits: number; ms: number }[];
  byPath: { key: string; label: string; hits: number; ms: number }[];
  byToken: { key: string; label: string; hits: number; ms: number }[];
  heat: { key: string; label: string; hits: number; ms: number }[];
  generatedAt: string;
};

const COLORS = ['#FFC53D', '#FF8A5B', '#4EA8DE', '#6FCF97', '#8E7CC3', '#E85D75', '#56C596', '#7FB2F0'];

export default function DashboardPage() {
  const [rep, setRep] = useState<Report | null>(null);
  const [days, setDays] = useState(14);
  const [forbidden, setForbidden] = useState(false);
  const [seeded, setSeeded] = useState(false);

  useEffect(() => {
    (async () => {
      const res = await fetch(`/api/analytics?days=${days}`);
      if (res.status === 403) { setForbidden(true); return; }
      const data = await res.json();
      setRep(data.report);
      setSeeded(Boolean(data.seeded));
    })();
  }, [days]);

  if (forbidden) {
    return (
      <div className="grid h-screen place-items-center">
        <div className="panel p-6 text-center">
          <p className="mb-3">Os relatórios de análise de acesso são restritos a administradores.</p>
          <a className="rounded-xl bg-amber-400 px-4 py-2 font-bold text-slate-900" href="/">voltar</a>
        </div>
      </div>
    );
  }

  if (!rep) return <div className="grid h-screen place-items-center text-slate-400">carregando relatórios…</div>;

  const avg = rep.byProject.length
    ? Math.round(rep.byProject.reduce((a, b) => a + b.ms, 0) / rep.byProject.reduce((a, b) => a + b.hits, 0) / 1000)
    : 0;

  return (
    <main className="mx-auto max-w-7xl px-6 py-10">
      <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-3xl font-black">📊 Análise de acesso</h1>
          <p className="text-slate-400">
            Relatórios visuais gerados a partir do histórico de navegação de cada token.
          </p>
        </div>
        <div className="flex items-center gap-2 text-sm">
          <span className="text-slate-400">janela</span>
          <select value={days} onChange={(e) => setDays(Number(e.target.value))}
            className="rounded-xl border border-slate-600/50 bg-slate-900/60 px-3 py-2">
            <option value={7}>7 dias</option>
            <option value={14}>14 dias</option>
            <option value={30}>30 dias</option>
          </select>
          <a href="/admin" className="rounded-xl border border-slate-600/50 px-3 py-2">tokens</a>
          <a href="/map" className="rounded-xl border border-slate-600/50 px-3 py-2">floresta</a>
        </div>
      </div>

      {seeded && (
        <p className="mb-6 rounded-xl border border-sky-400/30 bg-sky-400/10 px-4 py-2 text-sm text-sky-100">
          O log ainda está vazio: os gráficos abaixo usam a série de demonstração. Navegue pelo mapa
          com um token e os dados reais passam a aparecer automaticamente.
        </p>
      )}

      <section className="mb-6 grid gap-4 md:grid-cols-4">
        {[
          { t: 'Acessos na janela', v: rep.total.toLocaleString('pt-BR'), i: '👣' },
          { t: 'Tokens distintos ativos', v: String(rep.uniqueTokens), i: '🔑' },
          { t: 'Projetos visitados', v: String(rep.byProject.length), i: '🌳' },
          { t: 'Tempo médio por projeto', v: `${avg}s`, i: '⏱️' }
        ].map((k) => (
          <div key={k.t} className="panel p-5">
            <div className="text-2xl">{k.i}</div>
            <div className="mt-1 text-2xl font-black text-amber-200">{k.v}</div>
            <div className="text-xs text-slate-400">{k.t}</div>
          </div>
        ))}
      </section>

      <section className="panel mb-6 p-6">
        <h2 className="mb-4 font-bold">Evolução diária dos acessos</h2>
        <div className="h-64">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={rep.byDay}>
              <CartesianGrid stroke="#2b3f7a" strokeDasharray="3 3" />
              <XAxis dataKey="date" stroke="#8aa0c8" fontSize={11} />
              <YAxis stroke="#8aa0c8" fontSize={11} />
              <Tooltip contentStyle={{ background: '#0d1430', border: '1px solid #2b3f7a', borderRadius: 12 }} />
              <Line type="monotone" dataKey="hits" stroke="#FFC53D" strokeWidth={3} dot={{ r: 3 }} name="acessos" />
            </LineChart>
          </ResponsiveContainer>
        </div>
      </section>

      <section className="mb-6 grid gap-6 lg:grid-cols-2">
        <div className="panel p-6">
          <h2 className="mb-4 font-bold">🌳 Projetos de maior interesse</h2>
          <div className="h-80">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={rep.byProject} layout="vertical" margin={{ left: 24 }}>
                <CartesianGrid stroke="#2b3f7a" strokeDasharray="3 3" />
                <XAxis type="number" stroke="#8aa0c8" fontSize={11} />
                <YAxis type="category" dataKey="label" stroke="#8aa0c8" fontSize={11} width={130} />
                <Tooltip contentStyle={{ background: '#0d1430', border: '1px solid #2b3f7a', borderRadius: 12 }} />
                <Bar dataKey="hits" name="visitas" radius={[0, 6, 6, 0]}>
                  {rep.byProject.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="panel p-6">
          <h2 className="mb-4 font-bold">🍩 Distribuição por item e página</h2>
          <div className="h-80">
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie data={rep.byPath} dataKey="hits" nameKey="label" cx="50%" cy="50%" outerRadius={110} label>
                  {rep.byPath.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                </Pie>
                <Tooltip contentStyle={{ background: '#0d1430', border: '1px solid #2b3f7a', borderRadius: 12 }} />
                <Legend />
              </PieChart>
            </ResponsiveContainer>
          </div>
        </div>
      </section>

      <section className="mb-6 grid gap-6 lg:grid-cols-2">
        <div className="panel p-6">
          <h2 className="mb-4 font-bold">🔑 Acessos por token</h2>
          <div className="h-72">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={rep.byToken}>
                <CartesianGrid stroke="#2b3f7a" strokeDasharray="3 3" />
                <XAxis dataKey="label" stroke="#8aa0c8" fontSize={10} interval={0} angle={-12} height={54} />
                <YAxis stroke="#8aa0c8" fontSize={11} />
                <Tooltip contentStyle={{ background: '#0d1430', border: '1px solid #2b3f7a', borderRadius: 12 }} />
                <Bar dataKey="hits" name="acessos" fill="#4EA8DE" radius={[6, 6, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="panel p-6">
          <h2 className="mb-4 font-bold">🔥 Ranking de interesse (top 5)</h2>
          <ol className="space-y-3">
            {rep.heat.map((h, i) => (
              <li key={h.key} className="panel-soft flex items-center gap-4 p-3">
                <span className="text-lg font-black text-amber-300">{i + 1}</span>
                <div className="flex-1">
                  <div className="text-sm font-semibold">{h.label}</div>
                  <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-700/50">
                    <div className="h-full rounded-full bg-gradient-to-r from-amber-300 to-rose-400"
                      style={{ width: `${(h.hits / (rep.heat[0]?.hits || 1)) * 100}%` }} />
                  </div>
                </div>
                <div className="text-right text-xs text-slate-400">
                  <div className="font-bold text-slate-200">{h.hits}</div>
                  <div>{Math.round(h.ms / 1000)}s</div>
                </div>
              </li>
            ))}
            {!rep.heat.length && <li className="text-sm text-slate-500">Sem dados suficientes.</li>}
          </ol>
        </div>
      </section>

      <p className="text-center text-xs text-slate-500">
        Relatório gerado em {new Date(rep.generatedAt).toLocaleString('pt-BR')} · janela de {rep.windowDays} dias
      </p>
    </main>
  );
}

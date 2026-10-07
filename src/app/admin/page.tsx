'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';

type Row = {
  id: string; label: string; role: 'admin' | 'guest'; state: string;
  active: boolean; createdAt: string; expiresAt: string | null; hits: number; note: string | null;
};

export default function AdminPage() {
  const router = useRouter();
  const [rows, setRows] = useState<Row[]>([]);
  const [forbidden, setForbidden] = useState(false);
  const [label, setLabel] = useState('');
  const [hours, setHours] = useState(48);
  const [role, setRole] = useState<'guest' | 'admin'>('guest');
  const [created, setCreated] = useState<{ label: string; plain: string } | null>(null);
  const [log, setLog] = useState<any[]>([]);

  async function load() {
    const res = await fetch('/api/tokens');
    if (res.status === 403) { setForbidden(true); return; }
    const data = await res.json();
    setRows(data.tokens ?? []);
    const lr = await fetch('/api/log');
    if (lr.ok) setLog((await lr.json()).logs ?? []);
  }
  useEffect(() => { load(); }, []);

  async function create() {
    const res = await fetch('/api/tokens', {
      method: 'POST', headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ label, hours, role })
    });
    const data = await res.json();
    if (data.ok) { setCreated({ label: data.token.label, plain: data.plain }); setLabel(''); load(); }
  }

  async function patch(id: string, body: any) {
    await fetch(`/api/tokens/${id}`, {
      method: 'PATCH', headers: { 'content-type': 'application/json' }, body: JSON.stringify(body)
    });
    load();
  }

  async function remove(id: string) {
    await fetch(`/api/tokens/${id}`, { method: 'DELETE' });
    load();
  }

  if (forbidden) {
    return (
      <div className="grid h-screen place-items-center">
        <div className="panel p-6 text-center">
          <p className="mb-3">Esta área é restrita a tokens com papel de administrador.</p>
          <a className="rounded-xl bg-amber-400 px-4 py-2 font-bold text-slate-900" href="/">voltar</a>
        </div>
      </div>
    );
  }

  const badge = (s: string) =>
    s === 'active' ? 'bg-emerald-400/20 text-emerald-200'
    : s === 'inactive' ? 'bg-slate-500/20 text-slate-300'
    : s === 'expired' ? 'bg-amber-400/20 text-amber-200'
    : 'bg-rose-500/20 text-rose-200';

  return (
    <main className="mx-auto max-w-6xl px-6 py-10">
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-black">🔑 Tokens de acesso</h1>
          <p className="text-slate-400">Gere acessos temporários, ative, desative ou programe a validade.</p>
        </div>
        <a className="rounded-xl border border-slate-600/50 px-4 py-2 text-sm" href="/map">floresta</a>
      </div>

      <section className="panel mb-8 p-6">
        <h2 className="mb-4 font-bold">Gerar novo token</h2>
        <div className="grid gap-3 md:grid-cols-4">
          <input value={label} onChange={(e) => setLabel(e.target.value)} placeholder="Destinatário (ex: Banca Avaliadora)"
            className="rounded-xl border border-slate-600/50 bg-slate-900/60 px-4 py-2 md:col-span-2" />
          <select value={hours} onChange={(e) => setHours(Number(e.target.value))}
            className="rounded-xl border border-slate-600/50 bg-slate-900/60 px-4 py-2">
            <option value={0}>sem prazo (só ativar/desativar)</option>
            <option value={1}>1 hora</option>
            <option value={24}>24 horas</option>
            <option value={48}>48 horas</option>
            <option value={168}>7 dias</option>
            <option value={720}>30 dias</option>
          </select>
          <select value={role} onChange={(e) => setRole(e.target.value as any)}
            className="rounded-xl border border-slate-600/50 bg-slate-900/60 px-4 py-2">
            <option value="guest">convidado</option>
            <option value="admin">administrador</option>
          </select>
        </div>
        <button onClick={create} className="glow-gold mt-4 rounded-xl bg-amber-400 px-6 py-2 font-bold text-slate-900">
          Gerar token
        </button>

        {created && (
          <div className="mt-4 rounded-xl border border-emerald-400/40 bg-emerald-400/10 p-4">
            <p className="mb-1 text-sm font-bold text-emerald-200">
              Token de {created.label} — visível apenas esta vez
            </p>
            <code className="block break-all rounded-lg bg-slate-950/80 px-3 py-2 font-mono text-xs text-emerald-100">{created.plain}</code>
            <button onClick={() => navigator.clipboard.writeText(created.plain)}
              className="mt-2 rounded-lg border border-emerald-400/40 px-3 py-1 text-xs text-emerald-100">copiar</button>
          </div>
        )}
      </section>

      <section className="panel mb-8 overflow-x-auto p-6">
        <h2 className="mb-4 font-bold">Tokens emitidos</h2>
        <table className="w-full text-left text-sm">
          <thead className="text-[11px] uppercase tracking-wider text-slate-400">
            <tr>
              <th className="pb-2">Destinatário</th><th className="pb-2">Papel</th><th className="pb-2">Estado</th>
              <th className="pb-2">Validade</th><th className="pb-2">Acessos</th><th className="pb-2">Ações</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id} className="border-t border-slate-700/40">
                <td className="py-2 font-semibold">{r.label}</td>
                <td className="py-2">{r.role}</td>
                <td className="py-2"><span className={`rounded-full px-2 py-0.5 text-xs ${badge(r.state)}`}>{r.state}</span></td>
                <td className="py-2 text-xs text-slate-400">{r.expiresAt ? new Date(r.expiresAt).toLocaleString('pt-BR') : 'sem prazo'}</td>
                <td className="py-2">{r.hits}</td>
                <td className="py-2">
                  <div className="flex flex-wrap gap-1 text-xs">
                    <button onClick={() => patch(r.id, { active: !r.active })} className="rounded-lg border border-slate-600/50 px-2 py-1 hover:bg-slate-700/40">
                      {r.active ? 'desativar' : 'ativar'}
                    </button>
                    <button onClick={() => patch(r.id, { expiresInHours: 24 })} className="rounded-lg border border-slate-600/50 px-2 py-1 hover:bg-slate-700/40">+24h</button>
                    <button onClick={() => patch(r.id, { expiresAt: null })} className="rounded-lg border border-slate-600/50 px-2 py-1 hover:bg-slate-700/40">sem prazo</button>
                    <button onClick={() => patch(r.id, { revoke: true })} className="rounded-lg border border-amber-400/50 px-2 py-1 text-amber-200 hover:bg-amber-400/10">revogar</button>
                    <button onClick={() => remove(r.id)} className="rounded-lg border border-rose-400/50 px-2 py-1 text-rose-200 hover:bg-rose-500/10">excluir</button>
                  </div>
                </td>
              </tr>
            ))}
            {!rows.length && (
              <tr><td colSpan={6} className="py-6 text-center text-slate-500">Nenhum token temporário emitido ainda.</td></tr>
            )}
          </tbody>
        </table>
      </section>

      <section className="panel p-6">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="font-bold">Histórico de navegação por token</h2>
          <a href="/dashboard" className="rounded-lg border border-slate-600/50 px-3 py-1 text-xs">abrir dashboards</a>
        </div>
        <div className="max-h-80 overflow-y-auto">
          <table className="w-full text-left text-xs">
            <thead className="sticky top-0 bg-slate-900/90 text-[10px] uppercase text-slate-400">
              <tr><th className="py-2">Quando</th><th className="py-2">Token</th><th className="py-2">Caminho</th><th className="py-2">Projeto</th><th className="py-2">Tempo</th></tr>
            </thead>
            <tbody>
              {log.map((l) => (
                <tr key={l.id} className="border-t border-slate-700/30">
                  <td className="py-1.5 text-slate-400">{new Date(l.ts).toLocaleString('pt-BR')}</td>
                  <td className="py-1.5">{l.label}</td>
                  <td className="py-1.5 font-mono">{l.path}</td>
                  <td className="py-1.5 text-amber-200">{l.projectSlug ?? '—'}</td>
                  <td className="py-1.5 text-slate-400">{Math.round((l.ms ?? 0) / 1000)}s</td>
                </tr>
              ))}
              {!log.length && <tr><td colSpan={5} className="py-6 text-center text-slate-500">Sem registros ainda.</td></tr>}
            </tbody>
          </table>
        </div>
      </section>
    </main>
  );
}

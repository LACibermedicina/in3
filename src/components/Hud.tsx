'use client';

export default function Hud({
  label, role, onLogout, tokens, plots
}: { label: string; role: string; onLogout: () => void; tokens: number; plots: number }) {
  return (
    <div className="panel pointer-events-auto flex items-center gap-4 px-4 py-2 text-xs">
      <div className="flex items-center gap-2">
        <span className="text-lg">🧊</span>
        <span className="font-black tracking-wide text-amber-200">IN³</span>
      </div>
      <span className="h-4 w-px bg-slate-600/60" />
      <span className="text-slate-300">
        {label} <span className={`ml-1 rounded-full px-2 py-0.5 ${role === 'admin' ? 'bg-amber-400/20 text-amber-200' : 'bg-sky-400/20 text-sky-200'}`}>{role}</span>
      </span>
      <span className="h-4 w-px bg-slate-600/60" />
      <span className="text-slate-300">🥇 tokens <b className="text-amber-300">{tokens.toLocaleString('pt-BR')}</b></span>
      <span className="text-slate-300">🟩 terrenos <b className="text-emerald-300">{plots}</b></span>
      <span className="h-4 w-px bg-slate-600/60" />
      <a className="rounded-lg border border-slate-600/50 px-2 py-1 text-slate-300 hover:bg-slate-700/40" href="/map">floresta</a>
      {role === 'admin' && (
        <>
          <a className="rounded-lg border border-slate-600/50 px-2 py-1 text-slate-300 hover:bg-slate-700/40" href="/admin">tokens</a>
          <a className="rounded-lg border border-slate-600/50 px-2 py-1 text-slate-300 hover:bg-slate-700/40" href="/dashboard">analise</a>
        </>
      )}
      <a className="rounded-lg border border-slate-600/50 px-2 py-1 text-slate-300 hover:bg-slate-700/40" href="/setup">setup</a>
      <button onClick={onLogout} className="rounded-lg bg-rose-500/20 px-2 py-1 text-rose-200 hover:bg-rose-500/30">sair</button>
    </div>
  );
}

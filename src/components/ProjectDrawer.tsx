'use client';
import type { ProjectLite } from './ForestCanvas';

export default function ProjectDrawer({
  p, onClose
}: { p: ProjectLite | null; onClose: () => void }) {
  if (!p) return null;
  const statusLabel = p.status === 'done' ? 'Concluido - recursos em uso' : p.status === 'building' ? 'Em construcao' : 'Fase inicial / curadoria';
  return (
    <aside className="panel rise absolute right-4 top-4 z-20 w-[22rem] max-w-[92vw] p-5">
      <div className="mb-3 flex items-start justify-between">
        <div>
          <h3 className="text-lg font-black text-amber-200">{p.name}</h3>
          <p className="font-mono text-[11px] text-slate-500">/p/{p.slug}</p>
        </div>
        <button onClick={onClose} className="rounded-lg border border-slate-600/50 px-2 py-1 text-xs text-slate-300 hover:bg-slate-700/40">fechar</button>
      </div>

      <div className="mb-3 flex flex-wrap gap-2 text-[11px]">
        <span className={`rounded-full px-2 py-0.5 ${p.heat === 'warm' ? 'bg-orange-400/20 text-orange-200' : 'bg-sky-400/20 text-sky-200'}`}>
          {p.heat === 'warm' ? '🔥 alto crescimento' : '❄️ renda estavel'}
        </span>
        <span className="rounded-full bg-slate-700/40 px-2 py-0.5 text-slate-300">{statusLabel}</span>
        {!p.released && <span className="rounded-full bg-rose-500/20 px-2 py-0.5 text-rose-200">nao liberado ao publico</span>}
      </div>

      <div className="mb-3">
        <div className="mb-1 flex justify-between text-xs text-slate-400"><span>Desenvolvimento</span><span>{p.progress}%</span></div>
        <div className="h-2 w-full overflow-hidden rounded-full bg-slate-700/50">
          <div className="h-full rounded-full bg-gradient-to-r from-amber-300 to-emerald-300" style={{ width: `${p.progress}%` }} />
        </div>
      </div>

      <div className="grid grid-cols-2 gap-2 text-sm">
        <div className="panel-soft p-3"><div className="text-[10px] text-slate-400">ROI estimado</div><div className="font-bold text-emerald-300">{p.roi}%</div></div>
        <div className="panel-soft p-3"><div className="text-[10px] text-slate-400">Tokens aportados</div><div className="font-bold text-amber-300">{p.tokensRaised.toLocaleString('pt-BR')}</div></div>
      </div>

      <a href={`/p/${p.slug}`} className="glow-gold mt-4 block rounded-xl bg-amber-400 px-4 py-2 text-center font-bold text-slate-900 hover:bg-amber-300">
        Abrir hotsite do projeto
      </a>
    </aside>
  );
}

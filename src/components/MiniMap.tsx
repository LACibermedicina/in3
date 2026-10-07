'use client';
import type { ProjectLite } from './ForestCanvas';

export default function MiniMap({ projects, active, onPick }: { projects: ProjectLite[]; active?: string; onPick?: (s: string) => void }) {
  const R = 26;
  return (
    <div className="panel-soft p-3">
      <div className="mb-2 text-[11px] font-bold tracking-wider text-slate-300">MINI-MAPA DA FLORESTA</div>
      <svg viewBox="0 0 100 100" className="h-40 w-full">
        <circle cx="50" cy="50" r={R} fill="#0f1a33" stroke="#2b3f7a" strokeWidth="0.6" />
        <circle cx="50" cy="50" r={R * 0.55} fill="#122045" stroke="#2b3f7a" strokeWidth="0.4" />
        {projects.map((p) => {
          const cx = 50 + p.x * 1.25;
          const cy = 50 + p.z * 1.25;
          const color = p.heat === 'warm' ? '#FF8A5B' : '#4EA8DE';
          const on = active === p.slug;
          return (
            <g key={p.id} onClick={() => onPick?.(p.slug)} className="cursor-pointer">
              <circle cx={cx} cy={cy} r={on ? 4.2 : 2.6} fill={p.status === 'done' ? color : 'none'} stroke={color} strokeWidth="1.1" />
              {p.status !== 'done' && <circle cx={cx} cy={cy} r={1.1} fill={color} />}
            </g>
          );
        })}
      </svg>
      <div className="mt-1 flex gap-3 text-[10px] text-slate-400">
        <span className="flex items-center gap-1"><i className="inline-block h-2 w-2 rounded-full bg-orange-400" /> crescimento</span>
        <span className="flex items-center gap-1"><i className="inline-block h-2 w-2 rounded-full bg-sky-400" /> renda</span>
      </div>
    </div>
  );
}

import { notFound } from 'next/navigation';
import { db } from '@/lib/store';

export default function ProjectHotsite({ params }: { params: { slug: string } }) {
  const store = db();
  const p = store.projects.find((x) => x.slug === params.slug);
  if (!p) notFound();

  const stage =
    p.status === 'done'
      ? { label: 'Casa na árvore concluída', tone: 'emerald', note: 'Recursos em uso e em produção.' }
      : p.status === 'building'
      ? { label: 'Casa na árvore em construção', tone: 'amber', note: 'Andaimes de madeira montados no tronco.' }
      : { label: 'Fase inicial · curadoria', tone: 'sky', note: 'Sementes plantadas, estrutura em preparação.' };

  return (
    <main className="mx-auto max-w-4xl px-6 py-12">
      <a href="/map" className="text-xs text-slate-400 hover:text-amber-200">← voltar para a floresta</a>

      <header className="mt-4 mb-8">
        <h1 className="text-4xl font-black">{p.name}</h1>
        <p className="mt-1 font-mono text-xs text-slate-500">{p.repo ?? `/p/${p.slug}`}</p>
        <div className="mt-4 flex flex-wrap gap-2 text-xs">
          <span className={`rounded-full px-3 py-1 ${p.heat === 'warm' ? 'bg-orange-400/20 text-orange-200' : 'bg-sky-400/20 text-sky-200'}`}>
            {p.heat === 'warm' ? '🔥 alto crescimento' : '❄️ renda estável'}
          </span>
          <span className="rounded-full bg-slate-700/40 px-3 py-1 text-slate-200">{stage.label}</span>
          <span className="rounded-full bg-slate-700/40 px-3 py-1 text-slate-300">
            {p.visibility === 'private' ? 'repositório privado' : 'repositório público'}
          </span>
          {!p.released && <span className="rounded-full bg-rose-500/20 px-3 py-1 text-rose-200">não liberado ao público</span>}
        </div>
      </header>

      <section className="panel mb-6 p-6">
        <h2 className="mb-2 font-bold">Resumo curado por IA</h2>
        <p className="text-sm text-slate-300">{p.summary}</p>
        <p className="mt-2 text-xs text-slate-500">{stage.note}</p>
      </section>

      <section className="mb-6 grid gap-4 sm:grid-cols-3">
        <div className="panel p-5">
          <div className="text-xs text-slate-400">Desenvolvimento</div>
          <div className="text-3xl font-black text-emerald-300">{p.progress}%</div>
          <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-700/50">
            <div className="h-full rounded-full bg-gradient-to-r from-amber-300 to-emerald-300" style={{ width: `${p.progress}%` }} />
          </div>
        </div>
        <div className="panel p-5">
          <div className="text-xs text-slate-400">ROI estimado</div>
          <div className="text-3xl font-black text-amber-300">{p.roi}%</div>
        </div>
        <div className="panel p-5">
          <div className="text-xs text-slate-400">Tokens aportados</div>
          <div className="text-3xl font-black text-sky-300">{p.tokensRaised.toLocaleString('pt-BR')}</div>
        </div>
      </section>

      <section className="panel p-6">
        <h2 className="mb-3 font-bold">Investir neste projeto</h2>
        <p className="mb-4 text-sm text-slate-400">
          Aportar tokens acelera a construção da casa na árvore: o percentual exibido na base do
          tronco sobe conforme o progresso real do projeto é sincronizado.
        </p>
        <div className="flex gap-3">
          <a href="/map" className="glow-gold rounded-xl bg-amber-400 px-5 py-2 font-bold text-slate-900">Ir ao lote no mapa</a>
          <a href="/" className="rounded-xl border border-slate-600/50 px-5 py-2 text-slate-200">Início</a>
        </div>
      </section>

      <p className="mt-8 text-center text-[11px] text-slate-500">
        Filosofia “Nada Nasce Público” · o projeto só é exibido publicamente após liberação expressa.
      </p>
    </main>
  );
}

'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

type Step = 0 | 1 | 2;

export default function SetupPage() {
  const router = useRouter();
  const [step, setStep] = useState<Step>(0);

  const [repoUrl, setRepoUrl] = useState('');
  const [pat, setPat] = useState('');
  const [repoInfo, setRepoInfo] = useState<any>(null);
  const [ghMsg, setGhMsg] = useState<string | null>(null);
  const [needsPat, setNeedsPat] = useState(false);
  const [ghBusy, setGhBusy] = useState(false);

  const [apiKey, setApiKey] = useState('');
  const [aiResults, setAiResults] = useState<any[]>([]);
  const [aiMsg, setAiMsg] = useState<string | null>(null);
  const [aiBusy, setAiBusy] = useState(false);

  async function checkGithub() {
    setGhBusy(true); setGhMsg(null); setRepoInfo(null);
    try {
      const res = await fetch('/api/setup/test-github', {
        method: 'POST', headers: { 'content-type': 'application/json' },
        body: JSON.stringify({ repoUrl, pat })
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        setGhMsg(data.error ?? 'Falha ao consultar o GitHub.');
        setNeedsPat(Boolean(data.private) && !pat);
        return;
      }
      setRepoInfo(data.repo);
      setNeedsPat(false);
      setGhMsg(null);
    } catch { setGhMsg('Falha de rede.'); }
    finally { setGhBusy(false); }
  }

  async function checkAi() {
    setAiBusy(true); setAiMsg(null); setAiResults([]);
    try {
      const res = await fetch('/api/setup/test-ai', {
        method: 'POST', headers: { 'content-type': 'application/json' },
        body: JSON.stringify({ apiKey })
      });
      const data = await res.json();
      setAiResults(data.results ?? []);
      if (data.ok) setAiMsg(`Provedor ativo: ${data.provider}`);
      else setAiMsg(data.error ?? 'Nenhum provedor respondeu.');
    } catch { setAiMsg('Falha de rede.'); }
    finally { setAiBusy(false); }
  }

  async function finish() {
    await fetch('/api/setup/complete', { method: 'POST' });
    router.push('/map');
  }

  const steps = ['Repositorio', 'Curadoria por IA', 'Concluir'];

  return (
    <main className="mx-auto max-w-3xl px-6 py-12">
      <h1 className="mb-1 text-3xl font-black">⚙️ Setup autônomo</h1>
      <p className="mb-6 text-slate-400">
        Conecte o repositório alvo. A IA assume a leitura, a curadoria e a tradução técnica —
        nada é publicado sem liberação expressa.
      </p>

      <ol className="mb-8 flex gap-2 text-xs">
        {steps.map((s, i) => (
          <li key={s} className={`flex-1 rounded-xl border px-3 py-2 text-center ${
            i === step ? 'border-amber-300/60 bg-amber-300/10 text-amber-200' : 'border-slate-600/40 text-slate-400'
          }`}>{i + 1}. {s}</li>
        ))}
      </ol>

      {step === 0 && (
        <section className="panel rise p-6">
          <label className="mb-1 block text-sm font-bold">URL do repositório GitHub</label>
          <input
            value={repoUrl} onChange={(e) => setRepoUrl(e.target.value)}
            placeholder="https://github.com/usuario/repositorio"
            className="mb-4 w-full rounded-xl border border-slate-600/50 bg-slate-900/60 px-4 py-3"
          />

          <label className="mb-1 block text-sm font-bold">
            Token de acesso do GitHub (PAT)
            <span className="ml-2 font-normal text-slate-400">— somente se o repositório for privado</span>
          </label>
          <input
            value={pat} onChange={(e) => setPat(e.target.value)} type="password"
            placeholder="ghp_••••••••••••"
            className={`mb-4 w-full rounded-xl border bg-slate-900/60 px-4 py-3 ${
              needsPat ? 'border-amber-300/70' : 'border-slate-600/50'
            }`}
          />
          <p className="mb-4 text-xs text-slate-500">
            O PAT é usado apenas nesta chamada e nunca é gravado no front-end. Em produção, injete
            via variável de ambiente do servidor.
          </p>

          <div className="flex gap-3">
            <button onClick={checkGithub} disabled={ghBusy || !repoUrl}
              className="glow-cool rounded-xl bg-sky-400 px-5 py-2 font-bold text-slate-900 disabled:opacity-40">
              {ghBusy ? 'Consultando…' : 'Testar conexão'}
            </button>
            <button onClick={() => setStep(1)} disabled={!repoInfo}
              className="rounded-xl border border-slate-600/50 px-5 py-2 text-slate-200 disabled:opacity-40">
              Avançar
            </button>
          </div>

          {ghMsg && <p className="mt-4 rounded-lg border border-amber-400/30 bg-amber-400/10 px-4 py-2 text-sm text-amber-100">⚠️ {ghMsg}</p>}

          {repoInfo && (
            <div className="panel-soft mt-4 grid grid-cols-2 gap-3 p-4 text-sm">
              <div><div className="text-[10px] text-slate-400">Repositório</div><b>{repoInfo.fullName}</b></div>
              <div><div className="text-[10px] text-slate-400">Visibilidade</div><b>{repoInfo.private ? 'privado' : 'público'}</b></div>
              <div><div className="text-[10px] text-slate-400">Linguagem</div><b>{repoInfo.language ?? '—'}</b></div>
              <div><div className="text-[10px] text-slate-400">Estrelas / forks</div><b>{repoInfo.stars} / {repoInfo.forks}</b></div>
              <div><div className="text-[10px] text-slate-400">Branch padrão</div><b>{repoInfo.defaultBranch}</b></div>
              <div><div className="text-[10px] text-slate-400">Último push</div><b>{new Date(repoInfo.pushedAt).toLocaleDateString('pt-BR')}</b></div>
            </div>
          )}
        </section>
      )}

      {step === 1 && (
        <section className="panel rise p-6">
          <p className="mb-4 text-sm text-slate-400">
            O sistema testa os provedores de curadoria em ordem de prioridade: chaves de ambiente do
            servidor e, se falharem, o endpoint público de fallback.
          </p>

          <label className="mb-1 block text-sm font-bold">Nova chave de API (opcional)</label>
          <input
            value={apiKey} onChange={(e) => setApiKey(e.target.value)} type="password"
            placeholder="cole aqui uma chave, se precisar rotacionar"
            className="mb-4 w-full rounded-xl border border-slate-600/50 bg-slate-900/60 px-4 py-3"
          />

          <div className="flex gap-3">
            <button onClick={checkAi} disabled={aiBusy}
              className="glow-gold rounded-xl bg-amber-400 px-5 py-2 font-bold text-slate-900 disabled:opacity-40">
              {aiBusy ? 'Testando…' : 'Testar provedores'}
            </button>
            <button onClick={() => setStep(0)} className="rounded-xl border border-slate-600/50 px-5 py-2 text-slate-200">Voltar</button>
            <button onClick={() => setStep(2)} className="rounded-xl border border-slate-600/50 px-5 py-2 text-slate-200">Avançar</button>
          </div>

          {aiMsg && <p className="mt-4 rounded-lg border border-sky-400/30 bg-sky-400/10 px-4 py-2 text-sm text-sky-100">{aiMsg}</p>}

          {aiResults.length > 0 && (
            <ul className="mt-4 space-y-1 text-xs">
              {aiResults.map((r) => (
                <li key={r.id} className="flex justify-between panel-soft px-3 py-2">
                  <span className="font-mono">{r.id}</span>
                  <span className={r.ok ? 'text-emerald-300' : 'text-rose-300'}>{r.detail} · {r.ms}ms</span>
                </li>
              ))}
            </ul>
          )}
        </section>
      )}

      {step === 2 && (
        <section className="panel rise p-6">
          <h2 className="mb-2 text-lg font-bold">Pronto para liberar o mapa</h2>
          <p className="mb-4 text-sm text-slate-400">
            O repositório conectado entra no acervo como um projeto em <b>fase inicial</b>, oculto do
            público até a liberação. Avatares, árvores e barra de progresso são gerados a partir da
            curadoria de IA.
          </p>
          <div className="flex gap-3">
            <button onClick={finish} className="glow-gold rounded-xl bg-amber-400 px-5 py-2 font-bold text-slate-900">
              Concluir e abrir a floresta
            </button>
            <button onClick={() => setStep(1)} className="rounded-xl border border-slate-600/50 px-5 py-2 text-slate-200">Voltar</button>
          </div>
        </section>
      )}
    </main>
  );
}

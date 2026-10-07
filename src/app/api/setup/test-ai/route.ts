import { NextResponse } from 'next/server';
import { db, persist } from '@/lib/store';

export const runtime = 'nodejs';

/**
 * Testa provedores de curadoria/traducao por IA em ordem de prioridade.
 * Nenhuma chave e embutida no codigo: a rota le SOMENTE variaveis de ambiente.
 * Chaves enviadas pelo formulario ficam em memoria desta requisicao.
 */
export async function POST(req: Request) {
  const body = await req.json().catch(() => ({}));
  const provided = String(body?.apiKey ?? '').trim();

  const candidates = [
    { id: 'gemini', env: 'GEMINI_API_KEY', key: provided || process.env.GEMINI_API_KEY || '', endpoint: 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent' },
    { id: 'google', env: 'GOOGLE_API_KEY', key: process.env.GOOGLE_API_KEY || '', endpoint: 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent' },
    { id: 'pollinations', env: '(sem chave)', key: 'public', endpoint: 'https://text.pollinations.ai/openai' }
  ];

  const results: Array<{ id: string; ok: boolean; detail: string; ms: number }> = [];

  for (const c of candidates) {
    if (!c.key) { results.push({ id: c.id, ok: false, detail: `variavel ${c.env} ausente`, ms: 0 }); continue; }
    const started = Date.now();
    try {
      const res =
        c.id === 'pollinations'
          ? await fetch(c.endpoint, {
              method: 'POST',
              headers: { 'content-type': 'application/json' },
              body: JSON.stringify({ model: 'openai', messages: [{ role: 'user', content: 'responda apenas: ok' }], max_tokens: 5 }),
              cache: 'no-store'
            })
          : await fetch(`${c.endpoint}?key=${encodeURIComponent(c.key)}`, {
              method: 'POST',
              headers: { 'content-type': 'application/json' },
              body: JSON.stringify({ contents: [{ parts: [{ text: 'responda apenas: ok' }] }] }),
              cache: 'no-store'
            });
      const ms = Date.now() - started;
      results.push({
        id: c.id,
        ok: res.ok,
        detail: res.ok ? 'disponivel' : `HTTP ${res.status}${res.status === 429 ? ' (limite de taxa)' : ''}`,
        ms
      });
      if (res.ok) {
        const store = db();
        store.settings.aiProvider = c.id;
        persist();
        return NextResponse.json({ ok: true, provider: c.id, results });
      }
    } catch {
      results.push({ id: c.id, ok: false, detail: 'falha de rede', ms: Date.now() - started });
    }
  }

  return NextResponse.json({
    ok: false,
    results,
    error: 'Nenhum provedor de IA respondeu. Informe uma nova chave de API para continuar.'
  }, { status: 502 });
}

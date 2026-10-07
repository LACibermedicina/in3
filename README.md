# 🧊 IN³ (Cube ³) — Estufa de Inovação · m3d.pro

Módulo web interativo do **IN³**: uma incubadora de projetos com acompanhamento de
investimentos **gamificado em floresta isométrica 3D low-poly** (casas na árvore, ilhas
flutuantes, cachoeiras e nuvens), mais um **fluxo de configuração inicial autônomo** e um
**sistema de tokens de acesso** com histórico de navegação e dashboards de análise.

---

## 1. Visão humana (não técnica)

- **Uma floresta, um projeto por árvore.** Cada projeto incubado vira uma árvore-bonsai única,
  gerada por uma semente determinística: nenhuma silhueta, cor de folhagem ou telhado se repete.
- **Você vê o estágio sem ler relatório.** Projeto em fase inicial = andaimes de madeira e escada
  no tronco. Em andamento = volume da casa crescendo. Concluído = casa colorida com bichinhos
  circulando, representando os recursos gerados pelo projeto.
- **Barra holográfica na base de cada tronco** mostra o percentual real de desenvolvimento.
- **Tokens dourados flutuantes** marcam lotes e projetos abertos a aporte.
- **A cor diz o temperamento do projeto.** Tons quentes = alto crescimento. Tons frios = renda estável.
- **Avatares geométricos** caminham pelos caminhos: outros investidores no mesmo ambiente.
- **Acesso é por convite.** Ninguém entra sem token. Quem administra gera tokens temporários,
  liga, desliga, dá prazo ou revoga a qualquer momento.
- **Tudo é registrado.** Cada navegação fica no histórico do token que a fez, e vira gráfico no
  painel de análise: quais projetos e itens despertam mais interesse.
- **Nada Nasce Público.** Projeto em curadoria inicial não aparece para visitantes até liberação expressa.

## 2. Deep dive técnico

**Stack:** Next.js 14 (App Router) · React 18 · Three.js via `@react-three/fiber` + `drei` ·
Tailwind CSS · Recharts · TypeScript · persistência em arquivo JSON (`data/store.json`).

### 2.1 Rotas

| Rota | Tipo | Função |
|---|---|---|
| `/` | cliente | Índice do sistema + entrada por token de acesso |
| `/setup` | cliente | Setup autônomo: GitHub → provedores de IA → liberação |
| `/map` | cliente (R3F, `ssr:false`) | Floresta isométrica 3D, HUD, mini-mapa, painel lateral |
| `/p/[slug]` | servidor | Hotsite do projeto com métricas curadas |
| `/admin` | cliente | Console de tokens: gerar, ativar/desativar, prazo, revogar, excluir + histórico |
| `/dashboard` | cliente | Gráficos de análise de acesso (linha, barras, pizza, ranking) |

### 2.2 API

| Endpoint | Método | Papel exigido | Descrição |
|---|---|---|---|
| `/api/auth/login` | POST | — | Valida token, emite cookie de sessão assinado (HMAC-SHA256, 8 h) |
| `/api/auth/logout` | POST | sessão | Encerra a sessão |
| `/api/auth/me` | GET | sessão | Dados do portador |
| `/api/projects` | GET | sessão | Acervo visível (filtro “Nada Nasce Público” para convidados) |
| `/api/log` | POST / GET | sessão / admin | Grava e lê o histórico de navegação por token |
| `/api/tokens` | GET / POST | admin | Lista e emite tokens temporários |
| `/api/tokens/[id]` | PATCH / DELETE | admin | Ativar, desativar, reprogramar prazo, revogar, excluir |
| `/api/analytics` | GET | admin | Relatório agregado da janela (7/14/30 dias) |
| `/api/setup/test-github` | POST | — | Valida repositório (público direto; privado exige PAT) |
| `/api/setup/test-ai` | POST | — | Testa provedores de IA em cascata com fallback |
| `/api/setup/complete` | POST | — | Marca o onboarding como concluído |

### 2.3 Segurança do acesso

1. **O token mestre nunca existe em texto puro no repositório.** `.env.example` traz apenas
   `MASTER_TOKEN_HASH` no formato `scrypt$salt$hash` (64 bytes, `node:crypto`), comparado com
   `timingSafeEqual`. O valor literal do token não aparece em página, bundle, comentário ou log.
2. **Tokens temporários** são gerados com `randomBytes(24)`, guardados **somente como hash**.
   O valor em texto puro é devolvido uma única vez, na resposta de criação.
3. **Estados possíveis:** `active` · `inactive` (desativado pelo admin) · `expired` (prazo
   programado vencido) · `revoked` (revogação definitiva). Reativar limpa a revogação.
4. **Sessão** é um cookie `httpOnly`, `sameSite=lax`, com payload assinado e expiração de 8 h.
   O `middleware.ts` rejeita requisições sem sessão válida em `/map`, `/admin`, `/dashboard` e `/p/*`
   e registra a navegação de cada token.
5. **Retenção do log:** últimas 20 000 entradas.
6. **RBAC:** convidados só enxergam projetos com `released = true`; analytics, emissão e gestão de
   tokens exigem papel `admin`.

Gere o hash do seu token mestre:

```bash
npm run hash-token -- "seu-token-mestre"
# cole o resultado em MASTER_TOKEN_HASH dentro de .env.local
```

### 2.4 Geração procedural das árvores

`rng(seed)` é um LCG determinístico alimentado por `project.seed`. Dele saem: número de camadas de
folhagem (3–5), altura do tronco, torção do bonsai, duas cores de folha da paleta quente ou fria,
escala da casa e cor do telhado. Por isso **nenhuma árvore se repete** e o mapa é estável entre
recarregamentos. Andaimes, escadas, volume parcial da casa, barra holográfica, tokens dourados,
bichinhos e avatares são montados por composição de primitivas low-poly (icosaedros, cilindros,
cones, cápsulas) com `flatShading` para o look facetado.

### 2.5 Rodando

```bash
cp .env.example .env.local     # preencha SESSION_SECRET e MASTER_TOKEN_HASH
npm install
npm run dev                    # http://localhost:3000
```

---

## 3. Chaves de API e credenciais

- **Nenhuma chave de API é embutida no código.** Os provedores de curadoria são lidos
  exclusivamente de variáveis de ambiente do servidor (`GEMINI_API_KEY` ou `GOOGLE_API_KEY`),
  com fallback para um endpoint público que não exige chave (`POLLINATIONS_ENDPOINT`).
- Chaves coladas no formulário do `/setup` permanecem em memória, naquela requisição, e nunca são
  persistidas nem devolvidas ao cliente.
- ⚠️ **Rotacione imediatamente qualquer chave que já tenha circulado em chat, print, repositório ou
  prompt.** Trate-as como comprometidas: revogue no provedor, emita uma nova e injete apenas por
  variável de ambiente (`GEMINI_API_KEY`), jamais no código-fonte ou no `.env.example`.

---

## 4. Estrutura

```
middleware.ts                # guarda de rota + log de navegação por token
src/lib/session.ts           # assinatura/verificação de sessão (Web Crypto, Edge-safe)
src/lib/auth.ts              # hash scrypt, verificação timing-safe, estados do token
src/lib/store.ts             # persistência JSON, tipos, seed de demonstração
src/lib/analytics.ts         # agregações do log -> relatórios dos dashboards
src/components/ForestCanvas.tsx  # árvores, ilhas, cachoeiras, nuvens, avatares
src/components/Hud.tsx           # HUD superior (saldo, terrenos, navegação)
src/components/MiniMap.tsx       # mini-mapa interativo da floresta
src/components/ProjectDrawer.tsx # painel lateral de métricas do projeto
scripts/hash-token.mjs       # gerador de MASTER_TOKEN_HASH
```

> Persistência em arquivo JSON é intencional para o scaffold rodar sem serviço externo.
> Para produção, troque `src/lib/store.ts` por Postgres/Prisma ou Redis mantendo a mesma interface.

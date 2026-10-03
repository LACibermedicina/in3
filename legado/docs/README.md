# 🧊 IN³ — Portfólio vivo da incubadora m3d.pro · `in.m3d.pro`

> **O que entra na m3d, sai ao cubo.**
> A IN³ é a incubadora da [m3d.pro](https://m3d.pro). Ela pega o que já existe dentro da m3d — protótipos, dados, código e pessoas — e eleva ao cubo (³): pesquisa, prototipagem rápida, validação e entrega em saúde digital.

Este repositório contém o **motor** do portfólio público (`in.m3d.pro`) e o **painel administrativo apartado** que define, item por item, o que pode ser visto pelo público.

---

## ⚡ Em 60 segundos

```bash
npm run fetch:github     # coleta a atividade real do GitHub (server-side, com token)
npm run build            # interpreta os projetos e monta index.html + admin.html
npm run verify           # prova que nenhum item privado vazou
npm run serve            # site em http://localhost:8787  · painel em /admin.html
```

Zero dependências externas. Só Node.js ≥ 18. O site final é **um único HTML autocontido** — sem CDN, sem framework, sem chamada de rede para renderizar.

---

## 🎯 O que o sistema entrega

| Pedido | Como foi entregue |
|---|---|
| Portfólio a partir de **endereços GitHub** e/ou **documentação/playbook** | `data/portfolio.source.json` associa cada item a um conjunto de repositórios (`repos`) e a um capítulo do `docs/PLAYBOOK.md` (`playbook`) |
| **Documentação completa do projeto** como item de portfólio | este `README.md` + `docs/PLAYBOOK.md` + `docs/ARQUITETURA.md` + `docs/DEPLOY.md` |
| Área para **engajamento e solicitação de detalhes** públicos e técnicos | seção *“Pedir detalhes”* na página pública → `POST /api/requests` → `data/requests.json`, visível no painel |
| **Página de apresentação pública** com últimas notícias em **linguagem humana** | feed gerado de commits e marcos, cada notícia com botão **“ver técnico”** que revela o registro original |
| **Sem expor os dados da página no GitHub** | `.gitignore` bloqueia `data/`, `portfolio.full.json`, `.env`. O repositório guarda o motor, não os dados |
| **Ilustrações e desenho imersivo no estilo m3d.pro** | logomarca SVG do cubo ³ com os gradientes da m3d (`#7CC6B4 → #11695C`), 12 ilustrações vetoriais originais, aurora animada, cubo girando, cores claras alegres e vibrantes |
| **Controle de acesso + página administrativa apartada** | `admin.html` (noindex) com WebCrypto SHA-256 + login de servidor com token de 60 min |
| **Página principal = apresentação do portfólio** (`in.m3d.pro`) | `index.html` é a home; o painel fica em rota separada `/admin.html` |
| **Logomarca INcubator (cubo³)** | cubo isométrico de 3 faces em gradientes m3d + face superior dourada marcada com `³` |
| **Últimas atualizações vinculadas aos projetos novos** | o feed é recalculado no build a partir dos itens marcados + commits; itens novos aparecem no topo |
| **Base no GitHub de `lacibermedicina`** | coletor lê `LACibermedicina` (27 repositórios públicos); nada é publicado sem o checkbox |
| **Checkbox “mostrar ao público”** | 4 camadas de aplicação (curadoria → build → verify → servidor) |
| **Mapa de interações vinculado ao calendário** | mapa de calor de 182 dias + tabela mês × projeto |
| **Tecnologias envolvidas** | badges por projeto + nuvem global com contagem |
| **README interpretado por IA** | camada humana + camada técnica por projeto (`tools/build.mjs`), via LLM com chave em variável de ambiente; fallback determinístico se não houver chave |
| **Estilo imersivo, animado, com emojis e ícones** | animações CSS, contadores, revelação no scroll, filtros, modais, tooltips |

---

## 🗂️ Estrutura

```
in3/
├── index.html                  ← SITE PÚBLICO (in.m3d.pro) — autocontido, só itens públicos
├── admin.html                  ← PAINEL ADMINISTRATIVO (rota apartada, noindex)
├── template/
│   ├── index.template.html     ← fonte do site público (recebe /*__DATA__*/ do build)
│   └── admin.template.html     ← fonte do painel
├── data/
│   ├── portfolio.source.json   ← CURADORIA: itens + checkbox mostrar_ao_publico  ⚠️ não versionar
│   ├── portfolio.json          ← saída pública do build                              ⚠️ não versionar
│   ├── portfolio.full.json     ← inclui itens privados (uso interno)                ⚠️ não versionar
│   ├── requests.json           ← pedidos de detalhes do formulário público           ⚠️ não versionar
│   └── github.repos.cache.json / github.readmes.cache.json / github.commits.cache.json
├── tools/
│   ├── fetch-github.mjs        ← coleta server-side (token nunca vai ao navegador)
│   ├── build.mjs               ← interpreta (IA opcional) + filtra + injeta nos templates
│   ├── verify.mjs              ← prova que nada privado vazou (falha com exit 1)
│   ├── publish.mjs             ← importa a curadoria exportada pelo painel e roda build+verify
│   └── server.mjs              ← servidor opcional (API pública filtrada + login + pedidos)
└── docs/
    ├── PLAYBOOK.md             ← playbook por projeto (documentação base)
    ├── ARQUITETURA.md          ← decisões técnicas
    └── DEPLOY.md               ← publicação em in.m3d.pro
```

---

## 🔐 Política de acesso — “mostrar ao público”

Todo item nasce **oculto**. A publicação acontece em quatro camadas independentes — se uma falhar, as outras seguram:

1. **Curadoria** (`data/portfolio.source.json`): `mostrar_ao_publico: true|false` e `mostrar_link_repo: true|false`.
2. **Build** (`tools/build.mjs`): filtra antes de injetar o JSON dentro do `index.html`. Itens privados **não existem** no arquivo público.
3. **Verificação** (`tools/verify.mjs`): lê o HTML gerado e **falha** se encontrar título privado ou URL de repositório não autorizada.
4. **Servidor** (`tools/server.mjs`): o endpoint `/api/public/portfolio` filtra de novo em tempo de execução.

> 📌 Regra prática: para publicar algo, marque o checkbox no painel → **Exportar curadoria** → `node tools/publish.mjs portfolio.full.json`. O script roda build + verify sozinho.

---

## 🤖 Leitura interpretada por IA (duas camadas)

Cada projeto recebe dois textos:

- **Camada humana** — 2 a 4 frases simples, sem jargão (“consulta e acompanhamento de pacientes a distância”, “organização de documentos e impostos com leitura automática dos papéis”).
- **Camada técnica** — repositórios, linguagens, volume versionado, nº de commits lidos, tópicos, dependências citadas no README e um convite ao pedido de detalhes.

Sem chave de IA, o build usa um **interpretador determinístico** (léxico de domínios + estatísticas reais dos repositórios) — nunca inventa fatos. Com chave, um LLM reescreve as duas camadas a partir dos mesmos dados:

```bash
export OPENAI_API_KEY=sk-...            # ou outra API compatível
export OPENAI_BASE_URL=https://api.openai.com/v1   # opcional
export OPENAI_MODEL=gpt-4o-mini                    # opcional
npm run build
```

---

## 🔑 Variáveis de ambiente

| Variável | Para que serve | Obrigatória |
|---|---|---|
| `GITHUB_TOKEN` | coletar repositórios/commits (inclusive privados, se autorizado) sem limite baixo de requisições | recomendada |
| `GITHUB_USER` | conta coletada (padrão `LACibermedicina`) | não |
| `OPENAI_API_KEY` / `OPENAI_BASE_URL` / `OPENAI_MODEL` | interpretação por IA em duas camadas | não |
| `ADMIN_PASSWORD` | senha do painel (o servidor guarda só o hash SHA-256) | **sim, em produção** |
| `PORT` | porta do servidor (padrão `8787`) | não |

Copie `.env.example` para `.env` e preencha. **O `.env` nunca é versionado e nunca é embutido no HTML** — o navegador não recebe token nenhum.

---

## 📰 Como nascem as “últimas notícias”

```
commits reais + marcos cadastrados + status dos itens públicos
        ↓  build.mjs
frase humana  ←→  registro técnico (revelado sob solicitação)
        ↓
feed ordenado por data, filtrável por projeto
```

Exemplo de par:
- **humano:** “Adicionado suporte a exportação de documentos — em Contos & Contas.”
- **técnico:** `M3D-contoscontas · 4f9a1c22 · feat: add .DEC export`

---

## 🚀 Publicação

Resumo (detalhes em `docs/DEPLOY.md`):

```bash
npm ci --omit=dev
npm run fetch:github
npm run build && npm run verify
ADMIN_PASSWORD="$(openssl rand -hex 16)" GITHUB_TOKEN=ghp_xxx PORT=8787 node tools/server.mjs
```

Depois aponte o subdomínio `in.m3d.pro` para o servidor (A/CNAME) e termine o TLS com Nginx/Caddy.

---

## ✅ Checklist antes de cada publicação

- [ ] `npm run verify` passou (sem vazamento de item privado)
- [ ] nenhum item com parceiro/dado sensível marcado como público
- [ ] `ADMIN_PASSWORD` alterada do padrão
- [ ] `.gitignore` cobrindo `data/`, `.env`, `*.full.json`
- [ ] `admin.html` com `<meta name="robots" content="noindex,nofollow">` (já incluso)

## 📜 Licença

Código do motor: uso interno m3d.pro / IN³. Os dados de portfólio, textos e ilustrações pertencem à m3d.pro.

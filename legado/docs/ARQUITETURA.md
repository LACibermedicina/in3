# 🏗️ Arquitetura & decisões técnicas

## Princípio central

> **O site público não tem backend obrigatório, não fala com o GitHub e não carrega nada de fora.**
> Tudo que o visitante vê já foi decidido e filtrado antes de virar HTML.

Isso resolve três exigências de uma vez: (1) *não expor dados da página no GitHub*, (2) *mostrar ao público somente o que o administrador marcar*, (3) *página rápida, sem dependências*.

## Fluxo de dados

```
GitHub API  ──(tools/fetch-github.mjs, token só no servidor)──▶  data/*.cache.json
                                                                        │
data/portfolio.source.json  ──(curadoria: checklist + repos + playbook)─┤
                                                                        ▼
                                                          tools/build.mjs
                                     ┌──────────────────────────────────┴─────────────────────────────┐
                                     │ interpretador determinístico (léxico + estatísticas reais)      │
                                     │ + camada opcional de IA (OPENAI_API_KEY / base compatível)      │
                                     └──────────────────────────────────┬─────────────────────────────┘
                                                                        ▼
                          filtrar mostrar_ao_publico ──▶ template/index.template.html ──▶ index.html   (público)
                                                                                       admin.html   (apartado)
                                                                                       data/portfolio.json (saída pública)
                                                                                       data/portfolio.full.json (interno)
                                                                        ▼
                                                       tools/verify.mjs  → exit 1 se vazar
```

## Por que HTML único e autocontido

- O host `in.m3d.pro` pode servir o `index.html` de qualquer lugar (estático, CDN, container) **sem build de runtime**.
- Nenhuma requisição de terceiros ⇒ nenhuma superfície de rastreio do visitante.
- As ilustrações são **SVG inline** e a logomarca também: nenhuma imagem externa, nada quebra fora do app.

## Camadas de acesso (defesa em profundidade)

| # | Onde | O que garante |
|---|---|---|
| 1 | `mostrar_ao_publico` na curadoria | intenção do administrador registrada em arquivo |
| 2 | filtro no `build.mjs` | item oculto **não existe** no HTML publicado |
| 3 | `verify.mjs` | build reprovado se título privado ou link de repo não autorizado aparecer |
| 4 | `server.mjs` (`/api/public/portfolio`) | filtra outra vez em runtime, para quem consumir via API |

Testado: rodando `verify.mjs` com o item `cirurgia-video` marcado como oculto, o título “SurgeryTube — acervo de vídeo cirúrgico” não aparece em `index.html` e a URL `github.com/LACibermedicina/surgeyTube` não aparece em lugar nenhum do HTML público.

## Autenticação do painel

- **Modo local (sem servidor):** a senha é verificada no navegador via `crypto.subtle.digest('SHA-256')`. Os itens ficam em **IndexedDB** — nada sai da máquina.
- **Modo servidor (recomendado em produção):** `POST /api/admin/login` compara o hash da senha com o hash de `ADMIN_PASSWORD` e devolve um **token aleatório de 24 bytes válido por 60 minutos**, guardado apenas em memória. Toda rota `/api/admin/*` e `/api/github/refresh` exige `x-in3-token`.
- O login de servidor é opcional: se o servidor não estiver no ar, o painel continua funcionando em modo local e a publicação é feita exportando o JSON e rodando `publish.mjs` no terminal.

## Interpretação por IA

- Mesmo insumo para as duas camadas: descrições dos repositórios, linguagens, tópicos, volume, até 100 mensagens de commit por repo e o README.
- **Fallback determinístico:** um léxico de domínios (`telemed`, `irpf`, `rssi`, `ocr`, `lgpd`, `vlm`…) produz a camada humana; as métricas e listas vêm direto da API. O sistema **nunca inventa números** — se um dado não existe, o texto é omitido.
- A saída da IA é validada (`humano` e `tecnico` não vazios) antes de entrar no build; em erro, o fallback assume e o console mostra o motivo.

## Mapa de interações

- **182 dias** (6 meses) em grade 7 × semana, cor proporcional ao número de registros do dia.
- A tabela mensal cruza mês × projetos ativos.
- Fonte: datas reais de commit (`commit.author.date`) + marcos cadastrados. Zero dados sintéticos.

## Riscos conhecidos e mitigação

| Risco | Mitigação |
|---|---|
| Vazar item oculto | 4 camadas + `verify.mjs` no CI |
| Token do GitHub exposto | token só lido de `process.env` no servidor; nunca injetado no HTML |
| README ausente em vários repos | o build cai no fallback e usa descrição/commits; campos vazios são omitidos em vez de inventados |
| Rate limit da API do GitHub | cache local (`data/github.*.cache.json`) + `GITHUB_TOKEN` opcional |
| Dado clínico em publicação | fichas do playbook com campo explícito “o que NÃO é público” |

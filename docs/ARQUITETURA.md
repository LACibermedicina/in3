# 🏗️ Arquitetura & decisões técnicas

## Princípio central

> **Nada é filtrado depois de sair do servidor.**
> A profundidade de informação que um visitante pode ler é decidida *antes* da montagem da página e
> *dentro* da consulta de busca. O HTML público nunca contém o que está bloqueado — nem visível, nem
> escondido, nem em JSON embutido.

Isso resolve três exigências de uma vez: (1) não expor dados internos, (2) mostrar ao público somente
o que o administrador marcar, (3) página rápida, sem dependências e sem chamada de rede para
renderizar.

---

## Fluxo de dados

```
GitHub API ──(tools/sincronizar.php · token só no servidor)──▶ data/semear/github.*.cache.json
                                                                         │
data/semear/portfolio.source.json ──(curadoria: campos + nível)──────────┤
data/semear/portfolio.full.json   ──(resumos público/técnico por IA)─────┤
                                                                         ▼
                                                          tools/semear.php
                                     ┌────────────────────────────────────┴───────────────────────────┐
                                     │ · repos            (inventário + visibilidade confirmada)      │
                                     │ · projetos         (camadas 1..4 + teto + checkbox)            │
                                     │ · projeto_repos     projeto ↔ repositório                      │
                                     │ · projeto_tech      tecnologias                                │
                                     │ · projeto_marcos    roadmap                                    │
                                     │ · busca_texto       texto em CAMADAS CUMULATIVAS (1..4)        │
                                     │ · busca_fts         FTS5 sobre a mesma base                    │
                                     └────────────────────────────────┬───────────────────────────────┘
                                                                      ▼
                                          public/index.php  (roteador único)
                            ┌──────────────────────┬──────────────┴──────────────┬──────────────────┐
                            ▼                      ▼                             ▼                  ▼
                    site público            hotsite /p/{slug}             API /api/v1/…      painel /console
                    (só liberados)          (camadas por profundidade)    (mesma política)   (clareza 1..4)
```

---

## O contrato de profundidade

```
profundidade_liberada = min( nível_divulgacao do projeto , clareza do visitante )

clareza anônima ........................ 1
clareza de usuário autenticado ......... 1..4 (definida no painel)
clareza de admin ....................... 4
clareza por link /acesso/{token} ....... eleva, temporariamente, para aquele projeto
```

| Papel | Clareza | Vê |
|---|---|---|
| Visitante anônimo | 1 | Camada 1 de todo projeto público |
| Usuário `leitor` | 1–4 | Até o teto dele **e** até o teto do projeto |
| `curador` | 1–4 | O mesmo, mais edição de projetos |
| `admin` | 4 | Tudo, incluindo notas internas |

O teto é do **projeto**: um item marcado como *institucional* (2) jamais publica arquitetura, ainda
que quem leia tenha clareza 4. A clareza é do **leitor**: mesmo um projeto de nível 4 não entrega
camada 3 para um anônimo.

---

## Índice de busca em camadas

`busca_texto` guarda **quatro linhas por projeto**, uma para cada escopo cumulativo:

| escopo | conteúdo |
|---|---|
| 1 | título, categoria, situação, resumo, marcos |
| 2 | escopo 1 + público-alvo, problema, solução, como funciona, validação |
| 3 | escopo 2 + arquitetura, integrações, tecnologias, repositórios |
| 4 | escopo 3 + privacidade, riscos, referência do playbook, notas internas |

A consulta (FTS5 ou LIKE) carrega sempre:

```sql
WHERE escopo <= MIN(:clareza, CASE p.nivel_divulgacao
          WHEN 'institucional_roadmap' THEN 1
          WHEN 'institucional'         THEN 2
          WHEN 'tecnico'               THEN 3
          ELSE 4 END)
  AND (:admin = 1 OR p.mostrar_ao_publico = 1)
```

Consequência direta: **o texto interno não está no conjunto pesquisável** de uma consulta anônima.
Não há como "pescar" camada 3 porque ela não participa da busca. `tools/verificar.php` testa isso com
termos sensíveis a cada execução.

---

## Roteamento

Um único front controller (`public/index.php`) com rotas explícitas:

| Rota | Escopo | Observação |
|---|---|---|
| `/` | público | Portfólio + cena voxel |
| `/p/{slug}` | público | Hotsite do projeto, recortado por profundidade |
| `/busca?q=` | público | Busca filtrada no SQL |
| `/tecnologias` | público | Nuvem de tecnologias |
| `/pedido` | público | Formulário de detalhamento |
| `/acesso/{token}` | público | Consome e eleva a clareza |
| `/sitemap.xml`, `/robots.txt` | público | **Não** mencionam o painel |
| `/api/v1/…` | público/autenticado | Mesma política do HTML |
| `/saude` | público | Estado do serviço |
| `/instalar` | público | Só existe enquanto não houver administrador |
| `IN3_ROTA_PAINEL` (padrão `/console`) | autenticado | Rota não anunciada em nenhum lugar |

O painel **não** aparece em link, `robots.txt` ou sitemap. Trocar a rota é uma linha no `.env`.

---

## Sessão, CSRF e clareza

- Sessão de PHP com cookie `HttpOnly`, `SameSite=Lax` e `Secure` sob HTTPS.
- Além do cookie, há um **registro de sessão em banco** (`sessoes`) com token aleatório de 64 hex,
  IP, agente e expiração própria. Encerrar no banco invalida o cookie na hora.
- Um login ativo por usuário: abrir sessão nova apaga as anteriores.
- Todo POST de painel exige **token CSRF** de sessão (`hash_equals`).
- Tokens de liberação (`acessos`) têm prazo, limite de usos e são auditados a cada uso.

---

## Estados de visibilidade de repositório

Nada é adivinhado. `repos.visibilidade` assume exatamente três valores:

| valor | significado | como é obtido |
|---|---|---|
| `publico` | confirmado pela API pública do GitHub | `GET /users/{conta}/repos` |
| `privado` | confirmado por sincronização autenticada | token de leitura no `.env` |
| `pendente` | item declarado na curadoria e **não** confirmado | ausência de token para aquele repositório |

A coluna **Repositórios → passivo de curadoria** lista o que ainda está sem projeto. A visão geral
mostra a cobertura (`mapeados / total`) — hoje o inventário é 100 % mapeado, com os itens órfãos
entrando automaticamente como **não publicados**, para revisão humana.

---

## Ícones e padrões voxel

`src/icones.php` gera **SVG em linha** (24×24, `currentColor`) — sem fonte de ícone, sem CDN, sem
requisição. O mesmo arquivo produz o **padrão voxel determinístico** de cada projeto: uma semente
FNV-1a sobre o `slug` define alturas e paleta, de modo que o mesmo projeto gere sempre a mesma pilha
de cubos, sem guardar imagem em disco.

---

## Stack e por quê

| Camada | Escolha | Motivo |
|---|---|---|
| Servidor | **PHP 8.1+** | disponibilidade em qualquer hospedagem; sem build |
| Dados | **SQLite (WAL) + PDO** | um arquivo, transacional, zero administração |
| Busca | **FTS5** com plano B em `LIKE` | índice real quando existe; nunca quebra o site |
| Apoio | **Python 3** (na origem dos caches) | a coleta anterior já produzia os JSONs |
| 3D | **Three.js r128** local | cena interativa sem depender de rede |
| Estilo | CSS próprio, sem framework | paleta da marca, ~15 KB, zero requisições |

Python foi mantido como ferramenta de apoio (os caches `github.*.cache.json` e a curadoria
`portfolio.*.json` vieram desse pipeline); o runtime do sistema é PHP + SQLite, e a instalação não
exige Python.

---

## Decisões de projeto que você deve conhecer

1. **Não existe senha padrão.** O site responde com o aviso de instalação enquanto não houver
   administrador — e o instalador cria o primeiro sem credencial embutida.
2. **O token do GitHub nunca é persistido.** Ele vive no `.env` ou é digitado no painel, usado na
   chamada de sincronização e descartado.
3. **A API não é um caminho alternativo.** Ela chama a mesma montagem de camadas do HTML; não há
   rota que devolva o objeto cru.
4. **A cena 3D é decoração honesta.** Toda informação dela existe em texto na própria página.
5. **Legado preservado.** O motor anterior (arquivos anexados) está em `legado/` como referência
   histórica — não participa do runtime.

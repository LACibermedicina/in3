# 📖 Playbook · documentação base dos projetos da IN³

Este é o documento que o portfólio consome como **documentação base**. Cada item do acervo aponta
para um capítulo daqui (campo *Referência no playbook*, camada 4). O README de cada repositório é
cruzado com estas fichas.

**Regra deste playbook:** preencha apenas o que é verificável. Campo marcado *(pendente)* **não é
publicado** — o motor do portfólio (`src/projetos.php`, função `campo_publicavel`) descarta o campo
assim que encontra a marca, e `tools/verificar.php` confirma isso a cada execução.

---

## Modelo de ficha (copie para novos projetos)

```markdown
### Projeto · <NOME>
- **Categoria:** <Telemedicina | Fiscal | Comércio | Educação | Sensores | IA | Governança>
- **Status:** ativo | em incubação | protótipo | pesquisa | entregue | backlog | arquivado | infra
- **Repositórios:** `repo-a`, `repo-b`
- **Público-alvo:** <quem usa>
- **Problema:** <a dor real, em uma frase>
- **Solução:** <como o projeto resolve>
- **Como funciona (linguagem humana):** <sem jargão>
- **Arquitetura (técnico):** <stack, integrações, dados>
- **Validação:** <como sabemos que funciona>
- **Privacidade e LGPD:** <dados tratados, base legal, retenção>
- **Riscos conhecidos:** <o que pode dar errado e o que já mitiga>
- **Nível de divulgação:** 1 roadmap | 2 institucional | 3 técnico | 4 playbook
```

### Como preencher cada camada

| Camada | Campos | Publicado para |
|---|---|---|
| 1 · Roadmap institucional | título, categoria, situação, resumo, marcos | qualquer visitante |
| 2 · Institucional | público-alvo, problema, solução, como funciona, validação | quem pede e recebe liberação |
| 3 · Técnico | arquitetura, integrações, tecnologias, repositórios | quem pede e recebe liberação |
| 4 · Playbook completo | privacidade/LGPD, riscos, referência, notas internas | administração e liberação explícita |

---

## Onde as fichas vivem agora

No motor anterior cada ficha era um bloco de Markdown neste arquivo. No motor atual elas estão no
**banco** (`data/in3.db`, tabela `projetos`), editáveis no painel administrativo em
*Projetos › Editar*, e o texto é reindexado em camadas a cada gravação.

Vantagem prática: a mesma ficha alimenta o site público, a API, o hotsite e a busca — sem
duplicação e sem risco de a documentação divergir do que está publicado. Este arquivo passa a ser a
**metodologia** (como escrever a ficha), e o banco é o **conteúdo**.

### Passar do texto para o banco

```bash
# depois de editar as fichas aqui, replique o que for verificável no painel:
#   Projetos › Editar › preencher as camadas e marcar "Mostrar ao público"
php tools/semear.php      # reaplica a curadoria de data/semear/ sobre o banco
php tools/verificar.php   # confirma que nada interno vazou
```

---

## Acervo atual (confirmado na API pública do GitHub)

Inventário coletado de `LACibermedicina` em 2026-10-03. Visibilidade `publico` = confirmada pela API
pública; `pendente` = declarado na curadoria e ainda não confirmado (precisa de sincronização
autenticada). **Nada abaixo foi inventado** — nome, linguagem, descrição e data vêm da resposta da
API, e os caches ficam em `data/semear/github.*.cache.json`.

### Telemedicina & Saúde Digital

| Repositório | Linguagem | Descrição oficial |
|---|---|---|
| `tele.M3D.pro.final` | TypeScript | *(sem descrição no GitHub)* |
| `tele.M3D.pro` | TypeScript | Telemedicina |
| `zipTELE` | Shell | backup replit telemed |
| `CypherMED-Telemed` | TypeScript | *(sem descrição no GitHub)* |
| `saudeconectada` | TypeScript | *(sem descrição no GitHub)* |

### Fiscal & Finanças

| Repositório | Linguagem | Descrição oficial |
|---|---|---|
| `M3D-contoscontas` | TypeScript | App FastAPI+React para IRPF 2026. Upload ZIP, OCR inteligente, classificação docs, exportação .DEC/GCAP oficial Receita Federal |
| `contosecontasIRF` | TypeScript | *(sem descrição no GitHub)* |
| `Contos_Contas` | TypeScript | Repository for https://replit.com/@lucasmedicina20/Contas-Fiscais |
| `ContoContas-Fiscal` | TypeScript | Repository for https://replit.com/@queropaz/ContoContas-Fiscal |
| `Contos-Contas` | — | Repository for https://replit.com/@drlucasst/Contos&contas |
| `ContoConta` | — | Repository for https://replit.com/@drlucasst/ContoConta |
| `ContoContas` | — | *(sem descrição no GitHub)* |
| `Fiscal-Account` | TypeScript | ContoContas |

### Comércio & Vitrine

| Repositório | Linguagem | Descrição oficial |
|---|---|---|
| `shop.m3d` | TypeScript | *(sem descrição no GitHub)* |
| `catalogo` | TypeScript | *(sem descrição no GitHub)* |
| `shop` | PHP | catalogo base web |
| `show.m3d.pro` | Ballerina | *(sem descrição no GitHub)* |
| `sersocial` | — | Espelho do site fornecido pelo usuário |

### Instrumentação & Sensores

| Repositório | Linguagem | Descrição oficial |
|---|---|---|
| `WifiSpaceMapper` | Python | scan wifi surround |
| `surgeyTube` | — | repositorio de video para cirurgia |

### Educação Médica

| Repositório | Linguagem | Descrição oficial |
|---|---|---|
| `revalida.m3d` | TypeScript | *(sem descrição no GitHub)* |
| `LAMD` | — | Liga de medicina del deporte |
| `Emissor-de-Certificacao-de-Cursos` | — | Gerador de certificados de cursos e eventos com PHP, MySQL e Bootstrap (fork) |

### Pesquisa & IA

| Repositório | Linguagem | Descrição oficial |
|---|---|---|
| `moondream-img-` | — | tiny vision language model (fork) |
| `janAI` | — | Jan is an open source alternative to ChatGPT that runs 100% offline on your computer (fork) |
| `amc` | — | Adapted for healthTech usage (fork) |

### Governança & Infra

| Repositório | Linguagem | Descrição oficial |
|---|---|---|
| `LACibermedicina` | — | Config files for my GitHub profile. |
| `in3` | HTML | *(sem descrição no GitHub)* |

---

## Fichas (camada 1 disponível; demais camadas sob liberação)

### Projeto · Tele.M3D
- **Categoria:** Telemedicina & Saúde Digital
- **Status:** ativo
- **Repositórios:** `tele.M3D.pro.final`, `tele.M3D.pro`, `zipTELE`, `CypherMED-Telemed`, `saudeconectada`
- **Resumo:** plataforma de telemedicina de ponta a ponta, com atendimento clínico, colaboração médica
  e gestão do cuidado digital.
- **Marcos confirmados:** 2026-02-16 primeira versão no ar; 2026-07-13 consolidação do
  `tele.M3D.pro.final`; 2026-08-07 último avanço registrado no repositório principal.
- **Arquitetura (técnico):** *(liberação técnica — o README do repositório descreve a stack e os
  perfis de uso; consultar em *Pedir detalhamento*)*
- **Privacidade e LGPD:** *(pendente)*
- **Nível de divulgação:** 3 (técnico) — camadas 1 e 2 publicadas, camada 3 por liberação.

### Projeto · Contos & Contas
- **Categoria:** Fiscal & Finanças
- **Status:** ativo
- **Repositórios:** `M3D-contoscontas`, `contosecontasIRF`, `Fiscal-Account`, `Contos_Contas`, `ContoContas-Fiscal`
- **Resumo:** imposto de renda sem dor — upload de ZIP, OCR inteligente, classificação de documentos e
  exportação `.DEC`/`GCAP` no formato oficial da Receita Federal.
- **Marcos confirmados:** 2026-03-07 família Contos & Contas criada e prototipada; 2026-03-09
  exportação `.DEC`/`GCAP` oficial integrada.
- **Privacidade e LGPD:** *(pendente — envolve documentos fiscais pessoais; publicar somente após
  definição da política de retenção)*
- **Nível de divulgação:** 2 (institucional).

### Projeto · Loja & Catálogo
- **Categoria:** Comércio & Vitrine
- **Status:** ativo
- **Repositórios:** `shop.m3d`, `catalogo`, `shop`
- **Resumo:** vitrine de produtos de saúde; plataforma de lojas virtuais organizada por áreas de
  interesse e grupos de usuários. O `shop.m3d` é a versão *catálogo* do projeto.
- **Marcos confirmados:** 2026-09-01 catálogo iniciado; 2026-09-04 `shop.m3d` reescrito em
  TypeScript; 2026-10-02 `shop` (catálogo base web em PHP) atualizado.
- **Nível de divulgação:** 3 (técnico).

### Projeto · Revalida.M3D
- **Categoria:** Educação Médica
- **Status:** em incubação
- **Repositórios:** `revalida.m3d`
- **Resumo:** preparação para a prova de revalidação, com banco de questões e trilhas de estudo.
- **Marcos confirmados:** 2026-09-03 commit inicial; 2026-09-04 MVP pronto (conteúdo inicial);
  2026-09-05 atualização de conteúdo.
- **Nível de divulgação:** 2 (institucional).

### Projeto · WifiSpaceMapper
- **Categoria:** Instrumentação & Sensores
- **Status:** protótipo
- **Repositórios:** `WifiSpaceMapper`
- **Resumo:** o mapa invisível do ambiente clínico — portal web autoinstalável para mapeamento 3D de
  ambientes por CSI Wi-Fi, com controle de usuários, uma captura por projeto, réguas dinâmicas de
  medida, histórico de áreas mapeadas e exportação em CSV / JSON / PDF / XLSX / OBJ / PLY / STL.
  Interface em português, inglês, espanhol e catalão.
- **Marcos confirmados:** 2026-10-01 commit inicial (varredura de Wi-Fi no entorno); 2026-10-01
  leitura contínua do espaço.
- **Nível de divulgação:** 3 (técnico).

### Projeto · LAMD
- **Categoria:** Educação Médica
- **Status:** entregue
- **Repositórios:** `LAMD`
- **Resumo:** Liga Acadêmica de Medicina do Esporte — site institucional e conteúdo acadêmico, com
  projetos de extensão universitária (avaliação física e saúde na comunidade, atendimento a
  academias, clubes e escolas).
- **Marcos confirmados:** 2025-03-07 repositório publicado.
- **Nível de divulgação:** 2 (institucional).

### Projeto · Show.M3D.pro
- **Categoria:** Comércio & Vitrine
- **Status:** em incubação
- **Repositórios:** `show.m3d.pro`
- **Resumo:** a vitrine que se apresenta sozinha — página de apresentação em Ballerina.
- **Marcos confirmados:** 2026-09-10 criado e publicado.
- **Nível de divulgação:** 2 (institucional).

### Projeto · SerSocial
- **Categoria:** Comércio & Vitrine
- **Status:** protótipo
- **Repositórios:** `sersocial`
- **Resumo:** presença digital espelhada — espelho do site indicado pelo usuário, mantido por fluxo
  automático de espelhamento com suporte a Git LFS.
- **Marcos confirmados:** 2026-09-02 site espelhado e versionado (fluxo diário via GitHub Actions).
- **Nível de divulgação:** 2 (institucional).

### Projeto · Pesquisa aberta — modelos de visão e IA local
- **Categoria:** Pesquisa & IA
- **Status:** pesquisa
- **Repositórios:** `moondream-img-`, `janAI`, `amc` *(todos forks — base de estudo)*
- **Resumo:** base de estudo sobre visão computacional e IA executada localmente:
  `moondream-img-` (modelo de visão minúsculo), `janAI` (alternativa de código aberto ao ChatGPT,
  100 % offline) e `amc`.
- **Marcos confirmados:** 2025-04-12 `janAI` incorporado como base de estudo; 2026-04-20
  `moondream-img-` incorporado.
- **Nível de divulgação:** 2 (institucional).

### Projeto · SurgeryTube
- **Categoria:** Instrumentação & Sensores
- **Status:** backlog
- **Repositórios:** `surgeyTube`
- **Resumo:** acervo de vídeo cirúrgico — repositório criado para guardar vídeos de cirurgia.
- **Publicado:** **não** (item interno; aguarda definição de uso e consentimento de imagem)
- **Nível de divulgação:** 1 (roadmap institucional)

### Projeto · Emissor de Certificados de Cursos e Eventos
- **Categoria:** Educação Médica
- **Status:** arquivado
- **Repositórios:** `Emissor-de-Certificacao-de-Cursos` *(fork)*
- **Resumo:** gerador de certificados de cursos e eventos com PHP, MySQL e Bootstrap.
- **Publicado:** **não** (arquivado; mantido no acervo como histórico)
- **Nível de divulgação:** 1 (roadmap institucional)

### Projeto · Perfil institucional LACibermedicina
- **Categoria:** Governança & Infra
- **Status:** infra
- **Repositórios:** `LACibermedicina`
- **Resumo:** governança e configuração de perfil da conta que hospeda o acervo (`config`,
  `github-config`).
- **Marcos confirmados:** 2022-10-23 conta criada; 2026-09-02 configuração de perfil atualizada.
- **Nível de divulgação:** 1 (roadmap institucional)

---

## Registro de decisões de curadoria

| Data | Decisão | Motivo |
|---|---|---|
| 2026-10-03 | Motor reescrito em PHP + SQLite, com camadas e painel em rota apartada | Portfólio precisa de busca e liberação graduada; site estático não filtra |
| 2026-10-03 | Todo item nasce `mostrar_ao_publico = 0` | Política "só publica o que o administrador marcar" |
| 2026-10-03 | Repositórios passam a ter visibilidade **confirmada**, nunca presumida | Não inventar dado: `pendente` até a sincronização autenticada |
| 2026-10-03 | `SurgeryTube` e o emissor de certificados mantidos internos | Conteúdo sensível (imagem cirúrgica) e item arquivado |
| 2026-10-03 | Todo repositório sem ficha entra automaticamente como item interno | Evitar ponto cego no passivo de curadoria |

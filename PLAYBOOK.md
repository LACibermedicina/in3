# 📖 Playbook · documentação base dos projetos da IN³

Este é o documento que o portfólio consome como **documentação base**. Cada item do acervo
aponta para os repositórios reais da conta `LACibermedicina`. O que não está aqui — ou não
foi comprovado no inventário — entra no sistema como **`(pendente)`**, nunca inventado.

## Regras de curadoria

| data | decisão | motivo |
|---|---|---|
| 2026-10-03 | todo item nasce `mostrar_ao_publico = 0` | política "só publica o que o administrador marcar" |
| 2026-10-03 | repositórios têm visibilidade **confirmada**, nunca presumida | sem token, privado = `pendente` |
| 2026-10-03 | `SurgeryTube` e o emissor de certificados ficam internos | conteúdo sensível (imagem cirúrgica) / item arquivado |
| 2026-10-03 | todo repositório sem ficha entra automaticamente como item interno | evita ponto cego no acervo |
| 2026-10-03 | camada bloqueada não carrega corpo no servidor | o texto não deve nem existir na resposta |
| 2026-10-03 | nome de repositório só aparece com o checkbox *mostrar endereço* marcado | evita expor o mapa de código por padrão |

## Fichas (1 por projeto)

Campos iguais para todos: **o que é** (camada 1) · **contexto** (2) · **arquitetura** (3) ·
**playbook completo** (4). Abaixo, o que é **publicamente afirmável** hoje e o que **não é público**.

### 1. Tele.M3D — 🩺 — Telemedicina & Saúde Digital — *ativo* — **PÚBLICO**
* Repositórios: `tele.M3D.pro.final`, `tele.M3D.pro`, `zipTELE`, `CypherMED-Telemed`, `saudeconectada`
* É: plataforma de teleconsulta que conecta atendimento remoto, prontuário e acompanhamento clínico.
* **NÃO é público:** dados de pacientes, integrações com operadoras, arquitetura de
  autenticação clínica, critérios de validação em campo → camadas 3 e 4.

### 2. Contos & Contas — 🧾 — Fiscal & Finanças — *ativo* — **PÚBLICO**
* Repositórios: `M3D-contoscontas`, `contosecontasIRF`, `Fiscal-Account`, `Contos_Contas`, `ContoContas-Fiscal`
* É: motor contábil e fiscal — apuração, contas a pagar e receber, obrigações do dia a dia.
* **NÃO é público:** regras de negócio fiscais detalhadas, integrações com prefeituras,
  tratamento de dados de terceiros → camadas 3 e 4.

### 3. Loja & Catálogo — 🛍️ — Comércio & Vitrine — *ativo* — **PÚBLICO**
* Repositórios: `shop.m3d`, `catalogo`, `shop`
* É: vitrine e catálogo de produtos com montagem de loja e exposição de acervo.
* **NÃO é público:** meio de pagamento, precificação, fornecedores → camadas 3 e 4.

### 4. Revalida.M3D — 🎓 — Educação Médica — *em incubação* — **PÚBLICO**
* Repositório: `revalida.m3d`
* É: preparação guiada para provas médicas de revalidação, com trilhas de estudo e revisão.
* **NÃO é público:** banco de questões, metodologia de adaptação, métricas de acerto → 3 e 4.

### 5. WifiSpaceMapper — 📶 — Instrumentação & Sensores — *protótipo* — **PÚBLICO**
* Repositório: `WifiSpaceMapper`
* É: mapeia espaços reais a partir do sinal de rede, gerando planta de cobertura utilizável.
* **NÃO é público:** calibração do sensor, algoritmo de reconstrução de planta → camadas 3 e 4.

### 6. LAMD — 🏃 — Educação Médica — *entregue* — **PÚBLICO**
* Repositório: `LAMD`
* É: apoio digital ao laudo médico, com coleta estruturada e conferência do resultado.
* **NÃO é público:** estrutura do laudo, campos clínicos, conformidade regulatória → 3 e 4.

### 7. Show.M3D.pro — ✨ — Comércio & Vitrine — *em incubação* — **PÚBLICO**
* Repositório: `show.m3d.pro`
* É: página de apresentação do estúdio, em linguagem direta.
* **NÃO é público:** nenhum detalhe técnico relevante — projeto de fachada institucional.

### 8. SerSocial — 🌐 — Comércio & Vitrine — *protótipo* — **PÚBLICO**
* Repositório: `sersocial`
* É: camada social do ecossistema — relacionamento, comunidade e engajamento.
* **NÃO é público:** modelo de moderação, dados de usuários → camadas 3 e 4.

### 9. Pesquisa aberta — 🧠 — Pesquisa & IA — *pesquisa* — **PÚBLICO**
* Repositórios: `moondream-img-`, `janAI`, `amc`
* É: frente de pesquisa aberta em visão computacional e assistência por IA.
* **NÃO é público:** conjuntos de dados, hiperparâmetros, resultados preliminares → 3 e 4.

### 10. Perfil institucional — 🏛️ — Governança & Infra — *infra* — **PÚBLICO**
* Repositório: `LACibermedicina`
* É: perfil institucional do laboratório — identidade, acervo e canais de contato.
* **NÃO é público:** dados de contato pessoais, dados de pessoas da equipe → camadas 3 e 4.

### 11. SurgeryTube — 🎥 — *backlog* — **INTERNO**
* Repositório: `surgeyTube` · *não publicado*: contém vídeo cirúrgico (imagem de paciente).
* Política: só muda de estado com consentimento e revisão de LGPD explícitos.

### 12. Emissor de Certificados — 📜 — *arquivado* — **INTERNO**
* Repositório: `Emissor-de-Certificacao-de-Cursos` · item arquivado, sem curadoria de publicação.

### 13–16. Itens de consolidação (internos)
* `Contos-Contas` (`Contos-Contas`) · `ContoContas` (`ContoContas`) · `ContoConta` (`ContoConta`) —
  variações legacy do motor contábil, aguardando consolidação; teto **roadmap institucional**.
* `in3` (`in3`) — este próprio sistema de portfólio, mantido interno.

## Campos pendentes (não inventados)

Problema atacado · público-alvo · arquitetura detalhada · integrações · critérios de
validação · riscos · LGPD · notas internas. Cada um existe no sistema como
`(pendente de curadoria)` e é preenchido no painel → aba **Projetos** → camadas.

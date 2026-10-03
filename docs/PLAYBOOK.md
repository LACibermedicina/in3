# 📖 Playbook · documentação base dos projetos da IN³

Este é o documento que o portfólio consome como **documentação base**. Cada item de portfólio aponta para um capítulo daqui
(campo `playbook` em `data/portfolio.source.json`). O README de cada repositório é interpretado pela IA e cruzado com estas fichas.
**Preencha apenas o que é verificável.** O que estiver marcado *(pendente)* não é publicado.

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
- **Privacidade/LGPD:** <dados tratados, base legal, retenção>
- **Métricas observadas:** <o que medimos>
- **Riscos:** <o que pode dar errado>
- **Próximos passos:** <backlog curto>
- **O que NÃO é público:** <explicitamente fora>
```

---

<a id="tele"></a>
## 🩺 tele-m3d · Telemedicina de ponta a ponta

- **Categoria:** Telemedicina & Saúde Digital · **Status:** ativo
- **Repositórios:** `tele.M3D.pro.final`, `tele.M3D.pro`, `zipTELE`, `CypherMED-Telemed`, `saudeconectada`
- **Problema:** atendimento clínico a distância costuma ser fragmentado — vídeo de um lado, prontuário de outro, histórico perdido.
- **Solução:** um fluxo único de consulta remota: sala de vídeo, registro clínico, acompanhamento e continuidade do cuidado.
- **Como funciona (linguagem humana):** o paciente entra na sala virtual, conversa com o profissional por vídeo e o que foi falado fica registrado no mesmo lugar — na próxima consulta, ninguém começa do zero.
- **Arquitetura (técnico):** aplicação TypeScript, cliente de vídeo em tempo real, camada de prontuário, módulos de fila/atendimento. Evidência coletada: 3 repositórios da linha tele (≈ 57 MB versionados, 49 registros de trabalho lidos via API na linha `shop.m3d`/tele combinada).
- **Validação:** *(pendente)* registrar número de sessões de teste e tempo médio de conexão.
- **Privacidade/LGPD:** dados clínicos são sensíveis (art. 11). Áudio/vídeo só com consentimento explícito; retenção configurável; nenhum dado de paciente neste repositório.
- **Riscos:** qualidade de rede do paciente; interoperabilidade com sistemas hospitalares legados.
- **Próximos passos:** consolidação de `tele.M3D.pro.final` como linha oficial; trilha de auditoria de acesso.
- **O que NÃO é público:** credenciais de salas, dados de pacientes, contratos de parceiros.

<a id="fiscal"></a>
## 🧾 contos-e-contas · Imposto de renda sem dor

- **Categoria:** Fiscal & Finanças · **Status:** ativo
- **Repositórios:** `M3D-contoscontas`, `contosecontasIRF`, `Fiscal-Account`, `Contos_Contas`, `ContoContas-Fiscal`
- **Problema:** reunir comprovantes, classificar documentos e gerar a declaração consome dias e gera erro humano.
- **Solução:** upload de um ZIP com os documentos, leitura automática (OCR), classificação e exportação no formato oficial da Receita Federal.
- **Como funciona (linguagem humana):** você junta os papéis em um arquivo, joga no sistema e ele lê cada documento, separa por tipo e devolve o arquivo pronto para entregar ao Leão. 🦁
- **Arquitetura (técnico):** backend FastAPI + front React (TypeScript); OCR para extração de campos; gerador de `.DEC`/GCAP.
- **Validação:** *(pendente)* taxa de acerto por tipo de documento.
- **Privacidade/LGPD:** documentos fiscais são dados pessoais; processamento local/servidor dedicado; retenção mínima; exclusão sob demanda.
- **Riscos:** variação de layout dos comprovantes; mudanças de regra da Receita.
- **Próximos passos:** consolidar a família de protótipos (`ContoContas`, `Contos-Contas`, `contosecontasIRF`) em um único projeto canônico.
- **O que NÃO é público:** documentos de contribuintes, chaves de API, dados bancários.

<a id="loja"></a>
## 🛍️ loja-catalogo · Vitrine de produtos de saúde

- **Categoria:** Comércio & Vitrine · **Status:** ativo
- **Repositórios:** `shop.m3d`, `catalogo`, `shop`
- **Solução:** catálogo web com listagem de produtos, categorias e carrinho.
- **Como funciona (linguagem humana):** é a vitrine digital — mostra o que existe, organiza por categoria e permite montar o pedido.
- **Arquitetura (técnico):** reescrita em TypeScript sobre base PHP legada (`shop`); catálogo versionado como projeto maior da linha (≈ 46 MB).
- **Privacidade/LGPD:** só dados de pedido; nenhum dado de saúde.
- **Próximos passos:** unificar `catalogo` e `shop.m3d`; integrar com o site institucional.
- **O que NÃO é público:** preços de negociação e condições comerciais.

<a id="revalida"></a>
## 🎓 revalida-m3d · Preparação para a prova de revalidação

- **Categoria:** Educação Médica · **Status:** em incubação
- **Repositórios:** `revalida.m3d`
- **Solução:** trilhas de estudo e banco de questões orientados à prova de revalidação médica.
- **Como funciona (linguagem humana):** funciona como um treino guiado — o candidato responde questões e revisa os pontos fracos.
- **Arquitetura (técnico):** aplicação TypeScript com módulo de questões e progresso.
- **Próximos passos:** importar banco de questões; modo simulado cronometrado.
- **O que NÃO é público:** banco de questões licenciado.

<a id="wifi"></a>
## 📶 wifi-space-mapper · O mapa invisível do ambiente

- **Categoria:** Instrumentação & Sensores · **Status:** protótipo
- **Repositórios:** `WifiSpaceMapper` (Python)
- **Problema:** a cobertura de rede em clínicas e hospitais é crítica (telemonitoramento, prontuário em leito) e normalmente é avaliada “a olho”.
- **Solução:** varredura contínua do sinal Wi-Fi do entorno para desenhar o mapa de cobertura do espaço.
- **Como funciona (linguagem humana):** o programa caminha pelo ambiente medindo a força do sinal e devolve um mapa de onde a rede pega bem e onde não pega.
- **Arquitetura (técnico):** Python, leitura de RSSI, agregação por ponto de coleta.
- **Próximos passos:** exportar planta do mapa; integrar com `WifiSpaceMapper` → relatório de engenharia clínica.
- **O que NÃO é público:** credenciais de rede e SSIDs de clientes.

<a id="lamd"></a>
## 🏃 lamd · Liga Acadêmica de Medicina do Esporte

- **Categoria:** Educação Médica · **Status:** entregue
- **Repositórios:** `LAMD`
- **Solução:** presença digital e acervo de conteúdo da liga acadêmica.
- **Próximos passos:** publicar cronograma de eventos.
- **O que NÃO é público:** dados de alunos.

<a id="show"></a>
## ✨ show-m3d-pro · A vitrine que se apresenta sozinha

- **Categoria:** Comércio & Vitrine · **Status:** em incubação
- **Repositórios:** `show.m3d.pro` (Ballerina), `sersocial`
- **Solução:** páginas de apresentação de produto/serviço no domínio m3d.pro, com espelhamento versionado de sites fornecidos por parceiros.
- **Próximos passos:** reaproveitar os componentes desta página pública.

<a id="ia"></a>
## 🧠 pesquisa-ia · Modelos de visão e IA local

- **Categoria:** Pesquisa & IA · **Status:** pesquisa
- **Repositórios:** `moondream-img-` (modelo de visão minúsculo), `janAI` (alternativa offline ao ChatGPT), `amc` (base adaptada para healthtech)
- **Como funciona (linguagem humana):** são materiais de estudo e adaptação de modelos de IA que enxergam imagens ou rodam sem internet — base para apoio diagnóstico futuro.
- **Arquitetura (técnico):** pesos/modelos de terceiros incorporados como base de pesquisa; adaptações próprias para contexto de saúde.
- **Privacidade/LGPD:** IA local = dado não sai do dispositivo, o cenário preferido para dados clínicos.
- **Riscos:** licenças de terceiros; necessidade de validação clínica antes de qualquer uso assistencial.
- **O que NÃO é público:** nenhum dado de paciente; nenhum uso clínico declarado.

<a id="cirurgia"></a>
## 🎥 cirurgia-video · Acervo de vídeo cirúrgico *(oculto)*

- **Categoria:** Instrumentação & Sensores · **Status:** backlog · **Visibilidade:** **oculto ao público**
- **Motivo da ocultação:** vídeo cirúrgico contém imagem de paciente — só entra no site com consentimento e anonimização.

<a id="certificados"></a>
## 📜 certificados · Emissor de certificados *(oculto)*

- **Categoria:** Educação Médica · **Status:** arquivado · **Visibilidade:** **oculto ao público**
- **Solução:** gerador de certificados de cursos e eventos com PHP, MySQL e Bootstrap.

<a id="governanca"></a>
## 🏛️ perfil-organizacao · Governança & infraestrutura

- **Categoria:** Governança & Infra · **Status:** infra
- **Repositórios:** `LACibermedicina` (configuração de perfil)
- **Papel:** padronizar identidade, licenças e visibilidade das 27 linhas de repositório da conta.

---

## 🔁 Rotina de atualização

1. `npm run fetch:github` — puxa repositórios, READMEs e últimas 100 mensagens de commit por repositório.
2. Preencher as fichas `(pendente)` deste playbook que já tiverem evidência.
3. `npm run build` — a IA reescreve as camadas humana e técnica a partir dos dados frescos.
4. `npm run verify` — bloqueia se algum item oculto tentar aparecer.
5. `node tools/publish.mjs portfolio.full.json` — quando a mudança veio do painel.

# 🧊 IN³ — Portfólio vivo da incubadora m3d.pro · `in.m3d.pro`

> **O que entra na m3d, sai ao cubo.**
> A IN³ é a incubadora da [m3d.pro](https://m3d.pro): pega o que já existe dentro da m3d — protótipos,
> dados, código e pessoas — e eleva ao cubo (³): pesquisa, prototipagem rápida, validação e entrega em
> saúde digital.

Este repositório contém o **motor** do portfólio público (`in.m3d.pro`), o **hotsite de cada projeto**
com liberação por camadas, o **painel administrativo em rota apartada** e a **busca com controle de
acesso aplicado no servidor**.

Stack: **PHP 8.1+ · SQLite (PDO) · FTS5 · Python (ferramentas de apoio) · Three.js** — sem
dependências externas obrigatórias, sem CDN, sem framework.

---

## ⚡ Em 60 segundos

```bash
bash install.sh                 # confere ambiente, cria .env, banco, sincroniza e semeia
php tools/senha.php --usuario=SEU_USUARIO --criar --papel=admin --clareza=4
php -S localhost:8787 -t public public/index.php
# site público  → http://localhost:8787
# painel        → http://localhost:8787/console/entrar   (rota definida em IN3_ROTA_PAINEL)
```

No Windows: `install.bat` (mesma sequência).

---

## 🎯 As quatro decisões que definem o sistema

### 1. Todo projeto nasce **não publicado**
`mostrar_ao_publico = 0` é o padrão de fábrica. Só o que o administrador marca no painel aparece no
site público, na API pública e no arquivo de dados. Não existe "vazamento por esquecimento".

### 2. Quatro camadas de detalhamento, com teto por projeto

| Camada | Nome | O que entrega |
|---|---|---|
| 1 | `institucional_roadmap` | Identidade, área, situação e marcos. **Sempre pública.** |
| 2 | `institucional` | Problema, solução, público-alvo, como funciona em linguagem humana, validação. |
| 3 | `tecnico` | Arquitetura, integrações, tecnologias e repositórios do acervo. |
| 4 | `playbook_completo` | Ficha inteira: privacidade/LGPD, riscos, referência no playbook, notas internas (só admin). |

A profundidade servida é sempre `min(nível do projeto, clareza do visitante)`:

```
profundidade = min( teto definido na curadoria , clareza de quem lê )
```

Um projeto com teto 2 **nunca** publica camada 3, mesmo para um diretor com clareza 4. O teto é do
projeto; a clareza é do leitor; o menor valor manda.

### 3. O conteúdo bloqueado **não sai do servidor**
Não existe "esconder com CSS" nem JSON com o texto completo. As seções bloqueadas são montadas no
servidor e simplesmente não são emitidas: a página mostra o bloco vazio com o aviso de que aquela
camada existe e não é servida naquela profundidade.

### 4. A busca filtra **dentro da SQL**
O índice guarda o texto do projeto em **camadas cumulativas** (escopo 1, 2, 3 e 4). A consulta entra
com `escopo <= min(clareza, teto do projeto) AND (admin OR mostrar_ao_publico = 1)`. Uma busca
anônima não consegue alcançar texto interno porque esse texto nem participa do conjunto consultado.
Isso vale para o site, para o painel e para a API — o mesmo caminho de código.

---

## 🗺️ Mapa do projeto

```
in3/
├── public/
│   ├── index.php                front controller (site público + API + painel)
│   └── assets/
│       ├── css/in3.css          identidade visual (paleta oficial da marca)
│       ├── css/painel.css       complemento do painel
│       ├── js/voxel.js          cena voxel interativa em Three.js
│       ├── js/site.js           filtros e enriquecimento progressivo
│       ├── js/painel.js         ajuda de formulário do painel
│       └── vendor/three.min.js  Three.js servido localmente (r128, MIT)
├── src/
│   ├── nucleo.php               bootstrap, config, CSRF, sessão, log, segurança
│   ├── banco.php                PDO/SQLite (WAL), migrações, detecção de FTS5
│   ├── auth.php                 usuários, papéis, clareza, tokens de acesso
│   ├── projetos.php             curadoria, níveis, hotsite, indexação em camadas
│   ├── busca.php                busca com filtro de escopo aplicado em SQL
│   ├── icones.php               ícones SVG + padrões voxel determinísticos
│   ├── render.php               layouts e componentes
│   ├── api.php                  API JSON v1 (mesma política do HTML)
│   └── instalador.php           instalação em 2 etapas (web) e por CLI
├── views/                       21 views PHP (público, hotsite, painel, instalador)
├── data/
│   ├── schema.sql               esquema completo comentado
│   ├── in3.db                   banco (não versionar)
│   └── semear/                  caches do inventário GitHub (não versionar)
├── tools/
│   ├── instalar.php             aplica o esquema
│   ├── sincronizar.php          coleta o inventário GitHub (token só no servidor)
│   ├── semear.php               popula acervo a partir da curadoria + caches
│   ├── verificar.php            prova que nada interno vazou (exit 1 se vazar)
│   └── senha.php                cria/redefine senha pelo terminal (sem eco)
├── docs/
│   ├── ARQUITETURA.md           decisões técnicas e fluxo de dados
│   ├── SEGURANCA.md             modelo de ameaças e garantias
│   ├── DEPLOY.md                publicação em in.m3d.pro
│   └── PLAYBOOK.md              documentação base por projeto
├── install.sh / install.bat     autoinstaladores
├── .env.example                 configuração (copiar para .env)
└── legado/                      motor anterior (arquivos que você anexou), preservado
```

---

## 🔌 API JSON (v1)

| Rota | Acesso | Devolve |
|---|---|---|
| `GET /api/v1/publico/portfolio` | público | Projetos liberados, já recortados na profundidade |
| `GET /api/v1/publico/projeto/{slug}` | público | Um projeto com as camadas permitidas |
| `GET /api/v1/publico/busca?q=` | público | Resultados filtrados por escopo |
| `GET /api/v1/publico/tecnologias` | público | Contagem de tecnologias |
| `GET /api/v1/publico/meta` | público | Marca, níveis, categorias, cobertura de repositórios |
| `POST /api/v1/publico/pedidos` | público | Registra pedido de detalhamento |
| `POST /api/v1/publico/acesso` | público | Consome um token de liberação |
| `GET /api/v1/painel/portfolio` | autenticado | Todos os itens (respeitando a clareza) |
| `GET /api/v1/painel/repos` | autenticado | Inventário de repositórios |
| `POST /api/v1/painel/projeto` | admin/curador | Cria/atualiza item (exige CSRF) |

`GET /saude` devolve o estado do serviço (versão do esquema, FTS5, contagens).

---

## 🎛️ Painel administrativo

Rota apartada (`IN3_ROTA_PAINEL`, padrão `/console`), fora de qualquer link, do `robots.txt` e do
sitemap do site público. Seções: **Visão geral · Projetos · Repositórios · Pedidos · Busca interna ·
Usuários · Sistema**.

- **Projetos** — editor completo das quatro camadas, checkboxes de publicação, vínculo com o
  inventário de repositórios e marcos (`AAAA-MM-DD | texto`).
- **Repositórios** — todo o acervo GitHub com visibilidade confirmada e o *passivo de curadoria*
  (repositórios que ainda não pertencem a nenhum projeto).
- **Pedidos** — liberar um pedido emite um link `/acesso/TOKEN` com prazo e limite de usos, que eleva
  a clareza daquele visitante só para o projeto indicado.
- **Usuários** — papéis (`admin`, `curador`, `leitor`) e clareza (1 a 4). Senhas nunca são exibidas.
- **Sistema** — sincronização GitHub, reconstrução do índice, auditoria e tabelas do banco.

---

## 🔐 Liberação por link (`/acesso/{token}`)

```
Pedido público  →  painel: "Liberar" (camada 2, 3 ou 4 + prazo + limite de usos)
                →  link de uso controlado, expira sozinho, auditado
```

O eleva-clareza é gravado em `acessos` com `expira_em`, `usos` e `max_usos`. O visitante recebe só o
escopo daquele projeto — não ganha acesso ao acervo inteiro.

---

## 🧊 A cena voxel

`public/assets/js/voxel.js` monta uma cena Three.js em que **cada projeto é uma pilha de cubos** cuja
altura é o nível de detalhamento liberado e cuja paleta vem da categoria. É interativa, mas nunca
essencial:

- gira sozinha (pausa quando fora da tela ou com a aba oculta);
- arraste para girar, teclado para orbitar, `Enter` para pausar o giro, clique em um cubo para ir ao
  projeto;
- botões **Girar · Explodir · Repor**;
- `prefers-reduced-motion` e ausência de WebGL caem para modo estático;
- o texto alternativo e a lista de projetos abaixo descrevem exatamente o que a cena mostra.

---

## 🧰 Scripts

```bash
php tools/instalar.php        # aplica o esquema
php tools/sincronizar.php     # inventário GitHub (token via .env, nunca gravado)
php tools/semear.php          # acervo: curadoria + caches → banco
php tools/verificar.php       # PRIVACIDADE: falha (exit 1) se algo interno vazar
php tools/senha.php --usuario=nome [--criar --papel=admin --clareza=4]
```

`tools/verificar.php` é a trava de qualidade: confere montagem de camadas para todos os projetos,
roda buscas sensíveis (`LGPD`, `telemetria`, `arquitetura`, `riscos`, `privacidade`) simulando
visitante anônimo, varre o código por credenciais, valida que toda senha é hash e que a rota do
painel não aparece em arquivo público.

---

## 📜 Licença e uso

Projeto interno da IN³ / m3d.pro. O conteúdo do portfólio é publicado sob curadoria; nenhum dado
interno ou credencial é servido ao público.

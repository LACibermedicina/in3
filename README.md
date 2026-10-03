# 🧊 IN³ — Portfólio vivo da incubadora m3d.pro · `in.m3d.pro`

> **O que entra na m3d, sai ao cubo.**

Sistema de gestão de portfólio para a incubadora: o site público apresenta os projetos
da conta `LACibermedicina` em linguagem humana, e **cada projeto mostra apenas o que o
administrador liberar**. Todo item nasce interno; o painel é uma rota apartada, sem
nenhum vínculo no site público.

Stack: **PHP 8 + SQLite (WAL)** · JS vanilla · Python (auditoria) · zero dependências externas.

---

## 🚀 Subir em 3 comandos

```bash
unzip in3-incubator-php-sqlite.zip && cd in3
cp .env.example .env                 # troque IN3_ROTA_PAINEL pela sua rota secreta
./install.sh                         # Linux/macOS/WSL   (Windows: install.bat)
```

O instalador cria o `.env` (permissão 600), o banco `data/in3.db`, o acervo completo e a
conta **root**. No fim ele sobe o servidor e imprime as duas URLs:

```
Site público: http://localhost:8787
Painel:       http://localhost:8787/<IN3_ROTA_PAINEL>
```

Manualmente (sem o script):

```bash
php tools/instalar.php      # banco + acervo + conta root
php tools/verificar.php     # prova que nada interno vazou (bloqueante)
php -S 127.0.0.1:8787 -t public public/index.php
```

---

## 🔐 Acesso administrativo

| item | valor |
|---|---|
| Usuário root | `arcano` |
| Senha inicial | `arcano` (só quando o instalador roda sem terminal e sem `IN3_ROOT_SENHA`) |
| Troca obrigatória | **sim** — o painel só abre depois de definir uma senha própria (mín. 10 caracteres, letras + números) |
| Senha definida por você | `php tools/instalar.php --usuario=arcano --senha='SuaSenhaForte1'` (ou `IN3_ROOT_SENHA` no `.env`) |
| Armazenamento | **hash bcrypt (custo 12)** — nenhuma senha é exibida, exportada ou registrada em log |
| Redefinir depois | `php tools/senha.php --usuario=arcano` |
| Papel root | permissão `*` (tudo): projetos, camadas, publicação, usuários, sistema |

**Papéis e permissões** (checados sempre no servidor, nunca só na tela):

| papel | clareza máxima | pode |
|---|---|---|
| `root` | 4 | tudo (`*`) |
| `admin` | 4 | projetos, publicação, camadas, usuários, pedidos, sincronização |
| `curador` | 4 | criar/editar projetos e camadas, ver pedidos, busca ampla |
| `leitor` | 1–4 | ver projetos, busca dentro da própria clareza |

**As quatro camadas de detalhamento** — cada projeto tem as quatro; o teto define
até onde o visitante lê:

| camada | conteúdo | quem alcança |
|---|---|---|
| 1 · roadmap institucional | o que o projeto é, rumo, propósito | qualquer visitante (teto do anônimo) |
| 2 · institucional | problema, solução, público-alvo | liberação por link temporário |
| 3 · técnico | arquitetura, integrações, dados, validação | pedido de detalhe técnico / admin |
| 4 · playbook completo | riscos, LGPD, validações, notas internas | somente admin/root |

O visitante pede no formulário público → o curador clica **liberar** → nasce um link
`/acesso/TOKEN` válido por 7 dias que eleva a clareza **só daquele projeto**.

---

## 🧊 Página inicial (estilo voxel, câmera isométrica fixa)

* **Cena voxel isométrica sem rotação 2D** — cubos em `rotateX(58deg) rotateZ(-45deg)` fixo,
  um cubo por projeto publicado; hover levanta o cubo e revela o nome; cada cubo é um link
  navegável por teclado.
* **Imersão** — aurora animada em gradientes da marca, parallax discreto no scroll,
  profundidade em 3 faces por cubo, `prefers-reduced-motion` respeitado.
* **ASCII art interativa** — um cubo em bloco de caracteres montado a partir do texto que
  você digita; clicar numa célula alterna o voxel; existem botões *montar* e *limpar*.
* **Logo** — cubo isométrico com a face **³** dourada, em SVG puro
  (`assets/logo-in3.svg`), usado também como favicon.
* **Ícone por projeto** — o mesmo cubo com o glifo da categoria
  (🩺 🧾 🛍️ 🎓 📶 🏃 ✨ 🌐 🧠 🏛️ 🎥 📜).
* Paleta alegre e vibrante: `#59C1A5` `#2A9581` `#3AA3BA` `#FED166` sobre papel `#F7FFFC`.

---

## 🔎 Busca com filtro de acesso

`src/nucleo.php :: buscar($termo, $clareza, $admin)` roda **no servidor**, com
`profundidade <= clareza` e `mostrar_ao_publico = 1` quando o visitante é anônimo.
O trecho acima do escopo **nunca é lido do banco** — não é escondido no HTML, não existe
na resposta. Há a mesma busca dentro do painel, com a clareza do usuário logado.

---

## 🗂️ O que está dentro

```
in3/
├── install.sh · install.bat        autoinstalador (Linux/macOS/WSL · Windows)
├── public/
│   ├── index.php                   controlador frontal (site + painel + API JSON)
│   ├── robots.txt · .htaccess      endurecimento Apache
│   └── assets/  in3.css · in3.js · logo-in3.svg
├── src/
│   ├── nucleo.php                  config, banco, auth, RBAC, acervo, busca, ícones
│   ├── vistas.php                  HTML (layout, capa, hotsite, painel)
│   ├── rotas_publicas.php          / · /p/{slug} · /busca · /tecnologias · /pedido · /acesso/{token}
│   ├── rotas_painel.php            /<rota>/ entrar · projetos · pedidos · usuários · busca · sistema
│   └── instalador.php              instalação pela linha de comando
├── tools/
│   ├── instalar.php · semear.php · semear_lib.php
│   ├── sincronizar.php             inventário GitHub (token opcional, nunca gravado)
│   ├── verificar.php               verificação de privacidade (bloqueante)
│   ├── senha.php · exportar.php · auxiliar.py
├── docs/  PLAYBOOK.md · ARQUITETURA.md · SEGURANCA.md · DEPLOY.md
└── data/  in3.db (gerado) · semear/ (inventário de entrada)
```

### Rotas

| rota | o que é |
|---|---|
| `/` | página inicial: capa voxel, ASCII interativo, projetos, atualizações, tecnologias, FAQ |
| `/p/{slug}` | hotsite do projeto, montado só com as camadas permitidas |
| `/busca?q=` | busca filtrada pelo acesso |
| `/tecnologias` | nuvem de tecnologias dos projetos visíveis |
| `/pedido` | formulário de solicitação de detalhamento |
| `/acesso/{token}` | link temporário que eleva a clareza de um projeto |
| `/api/v1/publico/portfolio` | JSON público (já filtrado) |
| `/{IN3_ROTA_PAINEL}` | painel administrativo — **não divulgado, `noindex`, sem link no site** |

---

## ✅ Verificação (não é promessa, é execução)

```bash
php tools/verificar.php     # 9 checagens; reprovado = exit 1, não publique
```

1. nenhum item interno na listagem pública
2. camada bloqueada **sem conteúdo** servido ao anônimo
3. API pública nunca passa da camada 1 para o anônimo
4. busca anônima presa à camada 1 (7 termos sensíveis)
5. rota do painel ausente do HTML público **e** do `robots.txt`
6. nenhuma URL de repositório no HTML público enquanto desmarcada
7. todas as senhas gravadas como hash bcrypt
8. nenhum token/chave privada em arquivo do projeto
9. quatro camadas por projeto indexadas

Complemento em Python: `python3 tools/auxiliar.py --auditar --prova`.

---

## ⚙️ Variáveis de ambiente (`.env`)

| variável | para que serve |
|---|---|
| `IN3_ROTA_PAINEL` | rota secreta do painel (padrão `console` — troque) |
| `GITHUB_USER` | conta cujo acervo é inventariado (padrão `LACibermedicina`) |
| `GITHUB_TOKEN` | leitura de repositórios privados; usado só na sincronização, nunca gravado |
| `IN3_ROOT_SENHA` | senha da conta root criada pelo instalador (vazio = pergunta no terminal) |
| `PORT` · `IN3_CONFIA_PROXY` | porta do servidor · confiar em `X-Forwarded-For` (só atrás de proxy) |

---

## 🛡️ Política de privacidade em camadas

A publicação de um item passa por **quatro portas independentes**:

1. **curadoria** — o checkbox *mostrar ao público* grava a intenção no banco;
2. **montagem** — camada acima do teto sai da estrutura (`unset`), não vira HTML;
3. **verificação** (`tools/verificar.php`) — falha o build se algo privado aparecer;
4. **API** (`/api/v1/publico/portfolio`) — filtra de novo em tempo de execução.

Regra de honestidade: **o que não existe no inventário entra como `(pendente)`, nunca
inventado**. Sem token, repositórios privados ficam `pendente` — a API pública não os
devolve, então o sistema não afirma que existem.

---

## 📜 Licença e uso

Projeto interno da IN³ / m3d.pro. O conteúdo do portfólio é publicado sob curadoria;
nenhum dado interno ou credencial é servido ao público.

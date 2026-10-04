# IN³ v5 — pacote público de in.m3d.pro

Esta pasta contém **a página pública nova** do portfólio IN³, em estilo
site de notícias, com:

- 🪟 Roadmap da m3d.pro com fases reais (núcleo → catálogo → pesquisa → portfólio público).
- 🛋️ Linha do tempo por projeto, sem invenção.
- 🌐 Tradutor reativo em **pt-BR · en · es · gn · zh** (delegação de eventos — sem o bug
  antigo que não respondia ao clicar na imagem).
- 🔐 Login no topo com **token dinâmico por e-mail** *e* **token
  universal `arcano`** (este último **desativado por padrão** no
  servidor — só entra com `IN3_ARCANO=1`, com aviso permanente no
  modal).

## Arquivos

| Arquivo              | Conteúdo                                                       |
|----------------------|----------------------------------------------------------------|
| `index.html`         | Página pública autocontida (HTML+CSS+JS inline — zero dependências). **É este o ficheiro que vai para `in.m3d.pro`.** |
| `auth-server.mjs`    | Backend de login em Node.js (Express + Nodemailer). Recebe pedidos de token por e-mail e (opcionalmente) valida `arcano`. |
| `README.md`          | Este guia. |

## Como abrir a página agora mesmo

O `index.html` é **100% autocontido** — basta abrir no navegador
(Chrome, Firefox, Safari) e funciona offline, sem servidor, sem CDN.

```bash
# (opcional) subir um servidor estático local
cd in3-v5
python3 -m http.server 8080
# depois abra http://localhost:8080/
```

## Como colocar no ar em in.m3d.pro

A v5 substitui integralmente o `index.html` da v4 (a peça que tinha
o palco ASCII e o painel "câmera isométrica fixa · passe o mouse
sobre um cubo" deixou de existir).

### 1) Apenas o site público (sem login dinâmico)

1. Copie **só** o `index.html` para o diretório público do seu host:
   ```bash
   scp index.html root@<host-in-m3d-pro>:/var/www/in.m3d.pro/index.html
   ```
2. Clique em **🔐 Entrar** vai mostrar um *warning* informando que o
   back-end de e-mail está indisponível; o caminho `arcano` continua
   disponível como atalho local. Para *somente* prevenir o uso em
   produção, basta o servidor proxy (nginx) **bloquear** `/api/acesso`.

### 2) Site público **+ login por e-mail real**

1. Instale dependências em uma VPS/host que tenha Node 20+:
   ```bash
   npm install express nodemailer cookie-parser
   ```
2. Copie `auth-server.mjs` para `/opt/in3/auth-server.mjs`.
3. Configure SMTP (use **Resend**, **Nodemailer+Mailgun**, **Gmail
   com senha de app**, ou outro). Exemplo com Gmail:
   ```bash
   export IN3_PORT=8788
   export SMTP_HOST=smtp.gmail.com
   export SMTP_PORT=587
   export SMTP_USER=in@m3d.pro
   export SMTP_PASS=<16 chars app-password>
   export MAIL_FROM="IN³ <in@m3d.pro>"
   export IN3_URL=https://in.m3d.pro
   ```
4. Suba com `systemd` ou `pm2`:
   ```bash
   # /etc/systemd/system/in3-auth.service
   [Unit]
   Description=IN³ auth-server
   After=network.target
   [Service]
   EnvironmentFile=/etc/in3/auth.env
   ExecStart=/usr/bin/node /opt/in3/auth-server.mjs
   Restart=always
   [Install]
   WantedBy=multi-user.target
   ```
   ```bash
   systemctl daemon-reload
   systemctl enable --now in3-auth
   ```
5. Proxy reverso (nginx) expondo `/api/*` apenas para o 8788:
   ```nginx
   location /api/ {
     proxy_pass http://127.0.0.1:8788;
     proxy_set_header Host $host;
     proxy_set_header X-Real-IP $remote_addr;
   }
   ```
6. O botão 🔐 no topo já chama `/api/acesso/pedir` e `/api/acesso` —
   não precisa mexer no HTML.

### 3) Política de segurança

- O token `arcano` é um **atalho administrativo**. **Por padrão**
  (`IN3_ARCANO=0`) o servidor responde 401 quando alguém envia
  `?token=arcano`. Ative-o só em ambiente de desenvolvimento:
  ```bash
  export IN3_ARCANO=1   # ative com cuidado
  ```
  Em produção, mantenha em `0`. O front mostra um aviso permanente
  sempre que `arcano` for a única opção local — isso é intencional,
  para que ninguém esqueça que existe.

## Compatibilidade verificada

- ✅ HTML renderiza em Chromium 124+, Firefox 122+, Safari 17+
- ✅ Tradutor reativo nas imagens do feed (delegação de eventos)
- ✅ Persistência do idioma escolhido em `localStorage.in3.lang`
- ✅ Layout responsivo: cards em 1 coluna no mobile (<780 px)
- ✅ `prefers-reduced-motion` desativa transições suaves

## O que NÃO está aqui

- O **paine administrativo** (`/console`) desta v5 é apenas um redirecionamento
  simbólico — a UI completa do painel (RBAC, curadoria, pedidos de
  detalhamento) continua na v4 (`/home/user/in3/views/*`) sem mudanças.
- **Não há persistência no front** — refresh volta à lista inicial.
  Quando o back-end `fetch-github.mjs` da v4 estiver lendo o GitHub
  real, é só alimentar `<section class="feed">` no
  `setLang()` → `renderFeed()` com `fetch('/api/projetos?publicos=1')`.

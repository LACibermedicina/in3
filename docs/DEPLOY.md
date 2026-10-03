# 🚀 Deploy em `in.m3d.pro`

> ⚠️ **Não verifiquei DNS nem acesso ao host.** `getent hosts in.m3d.pro` retornou vazio a partir deste ambiente: o subdomínio
> ainda não resolve publicamente. Os passos abaixo são o caminho completo — a etapa de DNS e a instalação no servidor são suas.

## 1. Publicação estática (mais simples)

O `index.html` é autocontido: basta servi-lo.

```bash
npm ci --omit=dev
npm run fetch:github && npm run build && npm run verify
# suba index.html (+ admin.html) para a raiz do host de in.m3d.pro
```

Crie `in.m3d.pro` no seu provedor de DNS:
```
Tipo: CNAME   Nome: in   Valor: <host-do-seu-provedor-de-hospedagem>
```
ou, se for IP próprio:
```
Tipo: A       Nome: in   Valor: <IP do servidor>
```

## 2. Publicação com API + painel completo (recomendado)

```bash
git clone <seu-repo> /opt/in3 && cd /opt/in3
npm ci --omit=dev
cp .env.example .env
# edite .env: ADMIN_PASSWORD forte, GITHUB_TOKEN, OPENAI_API_KEY (opcional)
npm run fetch:github && npm run build && npm run verify

# serviço systemd
sudo tee /etc/systemd/system/in3.service >/dev/null <<'UNIT'
[Unit]
Description=IN3 portfolio
After=network.target
[Service]
WorkingDirectory=/opt/in3
EnvironmentFile=/opt/in3/.env
ExecStart=/usr/bin/node tools/server.mjs
Restart=always
User=www-data
[Install]
WantedBy=multi-user.target
UNIT
sudo systemctl enable --now in3
```

### Nginx

```nginx
server {
  listen 443 ssl http2;
  server_name in.m3d.pro;

  ssl_certificate     /etc/letsencrypt/live/in.m3d.pro/fullchain.pem;
  ssl_certificate_key /etc/letsencrypt/live/in.m3d.pro/privkey.pem;

  location / { proxy_pass http://127.0.0.1:8787; proxy_set_header Host $host; proxy_set_header X-Real-IP $remote_addr; }

  # o painel não deve ser indexado nem cacheado
  location = /admin.html { proxy_pass http://127.0.0.1:8787; add_header X-Robots-Tag "noindex, nofollow"; add_header Cache-Control "no-store"; }

  # dados internos nunca são servidos
  location ~ ^/(data|tools)/ { deny all; }
}
```

```bash
sudo certbot --nginx -d in.m3d.pro
```

## 3. Docker (alternativa)

```dockerfile
FROM node:22-alpine
WORKDIR /app
COPY . .
RUN npm ci --omit=dev && npm run build && npm run verify
EXPOSE 8787
CMD ["node","tools/server.mjs"]
```
```bash
docker build -t in3 .
docker run -d --name in3 --restart always -p 8787:8787 --env-file .env in3
```

## 4. Checklist pós-deploy

- [ ] `https://in.m3d.pro/` abre o portfólio com as animações
- [ ] `https://in.m3d.pro/admin.html` abre o login e responde **401** sem senha
- [ ] `https://in.m3d.pro/api/public/portfolio` **não** contém nenhum projeto oculto
- [ ] `https://in.m3d.pro/data/portfolio.full.json` retorna **403/404**
- [ ] `robots.txt` com `Disallow: /admin.html` e `Disallow: /api/`
- [ ] backup periódico de `data/portfolio.source.json` e `data/requests.json`

## 5. Fluxo de publicação de um item novo

1. Entre no painel (`/admin.html`) → aba **Novo item** → preencha repositório(s) e/ou playbook → marque **mostrar ao público**.
2. Aba **Itens** → **Exportar curadoria** (baixa `portfolio.full.json`).
3. No servidor:
   ```bash
   node tools/publish.mjs ~/portfolio.full.json
   ```
   Isso atualiza `data/portfolio.source.json`, roda o build e a verificação. Se algo privado vazar, o comando **falha** e não publica.

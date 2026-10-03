# 🚀 Deploy em `in.m3d.pro`

> A publicação real depende de DNS e do host, que **não são verificáveis deste ambiente**.
> Abaixo está o passo a passo completo, sem promessa de que o DNS já aponta para o servidor.

## 1. Preparar o host

```bash
unzip in3-incubator-php-sqlite.zip && cd in3
cp .env.example .env
# edite: IN3_ROTA_PAINEL (rota secreta), GITHUB_USER, IN3_URL_BASE=https://in.m3d.pro
chmod 600 .env

php tools/instalar.php                                   # cria banco + acervo + root
php tools/sincronizar.php --conta=LACibermedicina        # + --token=ghp_xxx p/ privados
php tools/semear.php
php tools/verificar.php                                  # precisa terminar OK (exit 0)
```

Requisitos: PHP **8.0+** com `pdo_sqlite`, `sqlite3`, `mbstring`. Nada mais.

## 2. Servidor de aplicação com systemd

`/etc/systemd/system/in3.service`:

```ini
[Unit]
Description=IN3 portfolio (in.m3d.pro)
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/in3
EnvironmentFile=/var/www/in3/.env
ExecStart=/usr/bin/php -S 127.0.0.1:8787 -t public public/index.php
Restart=always
# endurecimento mínimo
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=full
ReadWritePaths=/var/www/in3/data

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload && sudo systemctl enable --now in3
```

## 3. Nginx + TLS

```nginx
server {
  listen 443 ssl http2;
  server_name in.m3d.pro;

  root /var/www/in3/public;
  index index.php;

  # never serve o acervo interno nem as ferramentas
  location ~ ^/(data|tools|src|docs)/ { deny all; return 404; }
  location ~ /\.(env|git) { deny all; return 404; }

  location / {
    try_files $uri $uri/ /index.php?$query_string;
  }

  location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;   # ajuste à sua versão
  }

  add_header X-Content-Type-Options nosniff;
}
server { listen 80; server_name in.m3d.pro; return 301 https://$host$request_uri; }
```

Alternativa Apache: o `public/.htaccess` já vem com reescrita e bloqueio de `data/` e `tools/`.

TLS:

```bash
sudo certbot --nginx -d in.m3d.pro
```

## 4. DNS

Crie o registro `in` apontando para o host:

* `in  A    203.0.113.10` (IPv4 do servidor), ou
* `in  CNAME  seu-host.exemplo.com` se o provedor aceitar CNAME no subdomínio.

Confirme depois com `dig +short in.m3d.pro`. **Este ambiente não conseguiu resolver nem
acessar `in.m3d.pro`** — a verificação de DNS/TLS fica com você.

## 5. Rotina de atualização

```bash
cd /var/www/in3
php tools/sincronizar.php --conta=LACibermedicina     # inventário fresco
php tools/semear.php                                  # remapeia repositório → projeto
php tools/verificar.php || { echo "NÃO PUBLIQUE"; exit 1; }
python3 tools/auxiliar.py --auditar --prova            # relatório em texto
```

Sugestão de cron diário às 06:00. Se o verificador falhar, mantenha a versão anterior no ar:
**nunca publique com vazamento**.

## 6. Exportação estática (opcional)

Para uma cópia somente-leitura (CDN, espelho, arquivo):

```bash
php tools/exportar.php     # public/estatico/ — CSS/JS embutidos, clareza 1 (anônimo)
```

O export nunca inclui item interno nem camada acima da 1.

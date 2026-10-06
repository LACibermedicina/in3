# 🚀 Deploy em `in.m3d.pro`

> ⚠️ **Não verifiquei DNS nem acesso ao host.** Não houve como confirmar do ambiente de construção se
> `in.m3d.pro` já resolve publicamente. Os passos de DNS e a instalação no servidor são seus; abaixo
> está o caminho completo.

---

## 1. Publicação estática (mais simples)

O site público é renderizado por PHP, então há duas formas: servir com PHP (recomendado) ou exportar
o HTML já renderizado.

```bash
bash install.sh --so-preparar          # prepara banco, inventário, acervo e roda o verificador
php tools/senha.php --usuario=SEU_USUARIO --criar --papel=admin --clareza=4
php -S 0.0.0.0:8787 -t public public/index.php    # teste local
```

No DNS do provedor, crie:

```
Tipo: CNAME   Nome: in   Valor: <host-do-seu-provedor-de-hospedagem>
```

ou, com IP próprio:

```
Tipo: A       Nome: in   Valor: <IP do servidor>
```

---

## 2. Publicação com API + painel (recomendado)

```bash
cd /opt && git clone https://github.com/LACibermedicina/in3.git
cd /opt/in3
cp .env.example .env
# edite .env: IN3_ROTA_PAINEL (troque /console), GITHUB_TOKEN (leitura), IN3_URL_BASE
chmod 600 .env
php tools/instalar.php
php tools/sincronizar.php        # inventário GitHub (token lido do .env)
php tools/semear.php             # acervo + curadoria
php tools/verificar.php          # DEVE sair com sucesso antes de publicar
php tools/senha.php --usuario=SEU_USUARIO --criar --papel=admin --clareza=4
```

---

## 3. Apache

```apache
<VirtualHost *:443>
  ServerName in.m3d.pro
  DocumentRoot /opt/in3/public

  <Directory /opt/in3/public>
    AllowOverride All
    Require all granted
  </Directory>

  # tudo que não for arquivo existente vai para o front controller
  <IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
  </IfModule>

  # o banco e o .env não podem ser servidos
  <FilesMatch "\.(db|sqlite|env|sql|md)$">
    Require all denied
  </FilesMatch>
</VirtualHost>
```

Lembre de `a2enmod rewrite headers` e de permitir `AllowOverride` para os cabeçalhos.

---

## 4. Nginx + PHP-FPM

```nginx
server {
    listen 443 ssl http2;
    server_name in.m3d.pro;
    root /opt/in3/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/in.m3d.pro/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/in.m3d.pro/privkey.pem;

    client_max_body_size 4m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~* \.(db|sqlite|env|sql|md)$ { deny all; }
    location ~ /\.            { deny all; }

    add_header X-Content-Type-Options nosniff always;
}

server {
    listen 80;
    server_name in.m3d.pro;
    return 301 https://$host$request_uri;
}
```

---

## 5. Unit systemd (porta alta + proxy)

```ini
[Unit]
Description=IN3 portfolio (PHP built-in)
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/opt/in3
EnvironmentFile=/opt/in3/.env
ExecStart=/usr/bin/php -S 127.0.0.1:8787 -t public public/index.php
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now in3
curl -s localhost:8787/saude | php -r 'echo json_encode(json_decode(stream_get_contents(STDIN)), 128), "\n";'
```

---

## 6. Checklist antes de anunciar o site

- [ ] `php tools/verificar.php` sai com sucesso.
- [ ] `IN3_ROTA_PAINEL` trocado do padrão `/console`.
- [ ] `.env` fora do repositório, com permissão `600`.
- [ ] Senha de administrador forte e própria (não compartilhada).
- [ ] `IN3_URL_BASE` e `IN3_HOST` apontando para o domínio real.
- [ ] HTTPS ativo (cookie passa a `Secure` automaticamente).
- [ ] Nginx/Apache bloqueando `.db`, `.env`, `.sql` e `.md`.
- [ ] Backup agendado de `data/in3.db`.
- [ ] `IN3_CONFIA_PROXY=1` **somente** se houver proxy reverso confiável.
- [ ] Revisão de `Repositórios → passivo de curadoria` (itens sem projeto).
- [ ] Nível de divulgação conferido item por item antes de publicar cada ficha.

---

## 7. Operação do dia a dia

```bash
# atualizar o acervo com a atividade real do GitHub
php tools/sincronizar.php && php tools/semear.php && php tools/verificar.php

# redefinir a senha de alguém (sem eco no terminal)
php tools/senha.php --usuario=fulano

# estado do serviço
curl -s https://in.m3d.pro/saude
```

Rotina sugerida: cron diário às 06:00 rodando a tríade `sincronizar → semear → verificar`, com o
resultado por e-mail para quem cuida do acervo. Se o verificador falhar, o site continua no ar com o
conteúdo anterior — o que nunca pode acontecer é publicar com vazamento.

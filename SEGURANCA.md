# 🔐 Segurança — modelo de ameaças e garantias

## O que o sistema promete

1. **Nada nasce público.** Todo projeto é criado com `mostrar_ao_publico = 0`.
2. **A camada bloqueada não é enviada.** O corpo é removido no servidor antes de montar a página.
3. **A busca respeita o escopo no SQL.** O filtro é `profundidade <= clareza`, aplicado no `WHERE`.
4. **O painel não é anunciado.** Rota configurável, `noindex,nofollow`, ausente do `robots.txt`,
   do `sitemap.xml` e de qualquer HTML público.
5. **Credenciais nunca em claro.** Bcrypt custo 12; nenhum endpoint devolve senha; a senha do
   instalador só existe na memória do processo e vai para o banco como hash.
6. **Rastro de auditoria.** Login (ok/falha/bloqueio), criação/edição de projeto, liberação de
   pedido, mudanças de usuário e senha ficam em `auditoria`.

## Controles por camada

| risco | controle |
|---|---|
| força bruta | 8 falhas por usuário+IP em 15 min → bloqueio de 15 min, com atraso artificial de 300 ms por falha |
| sequestro de sessão | `HttpOnly` + `SameSite=Lax` + `Secure` sob HTTPS + `session_regenerate_id(true)` no login |
| CSRF | token de 32 bytes por sessão, validado em todo POST (`hash_equals`) |
| XSS | todo texto vindo do banco passa por `htmlspecialchars` na saída (`e()`); CSP `script-src 'self'` |
| clickjacking | `X-Frame-Options: DENY` + `frame-ancestors 'none'` |
| MIME sniffing | `X-Content-Type-Options: nosniff` |
| vazamento de link temporário | token de 24 bytes, uso único (`usado`), validade de 7 dias, elevando clareza **de um único projeto** |
| vazamento por git | `.gitignore` bloqueia `data/*.db`, `.env`; `tools/verificar.php` varre tokens (`ghp_`, `github_pat_`, `sk-`, chaves privadas) |
| diretório de dados exposto | `.htaccess` bloqueia `data/` e `tools/` no Apache; no Nginx, `location ~ ^/(data|tools)/ { deny all; }` |

## O que este sistema NÃO promete

* **Não é um WAF.** Um ataque volumoso derruba um host pequeno; use proxy/CDN à frente.
* **Não cifra o banco.** `data/in3.db` é o acervo inteiro em claro no disco: proteja por
  permissão de arquivo + backup cifrado.
* **Não substitui revisão jurídica de LGPD.** O sistema apoia a política (camadas, curadoria,
  auditoria); a decisão sobre o que publicar é humana — e é registrada.
* **Não confirma repositórios privados** sem token de leitura do dono.

## Rotina recomendada

```bash
# diária: sincroniza, remapeia e prova que nada vazou
php tools/sincronizar.php --conta=LACibermedicina [--token=ghp_xxx]
php tools/semear.php
php tools/verificar.php     # se falhar (exit 1): NÃO publique
```

**Backup:** `data/in3.db` é um arquivo único — copie-o (com `-wal`/`-shm` parados ou via
`sqlite3 .backup`). O `.env` guarda o token e a rota do painel: `chmod 600`.

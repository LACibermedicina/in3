# 🔐 Segurança — modelo de ameaças e garantias

## O que este sistema promete

1. **Nada interno é servido ao público.** Nem na página, nem no HTML oculto, nem em JSON embutido,
   nem no índice de busca. O filtro é aplicado no servidor, antes da montagem.
2. **Nenhuma credencial vive em código, documento, tela ou log.** Senhas existem apenas como hash;
   o token do GitHub é usado em memória e descartado.
3. **A superfície administrativa não é anunciada.** Rota fora de link, `robots.txt` e sitemap.
4. **Todo acesso é auditável.** Log de acessos, log de auditoria e histórico de tentativas de login.

---

## Modelo de ameaças

| Vetor | Tratamento |
|---|---|
| Visitar o site público e procurar dado interno | Montagem por camadas no servidor; o texto bloqueado não é emitido |
| Pesquisar termos sensíveis na busca pública | Índice em camadas + filtro de escopo na SQL; verificado a cada build |
| Alterar o `slug`/`id` na URL para abrir item privado | Consulta exige `mostrar_ao_publico = 1`; sem isso, 404 |
| Chamar a API para obter o objeto cru | API usa a mesma montagem; não existe rota que devolva o registro bruto |
| Força bruta na tela de login | Limite por usuário **e** por IP (janela de 15 min), tempo de resposta equalizado |
| Roubo de cookie de sessão | `HttpOnly`, `SameSite=Lax`, `Secure` sob HTTPS; token de sessão em banco com expiração |
| Reuso de sessão após troca de senha | Redefinir senha apaga todas as sessões do usuário |
| CSRF em formulário do painel | Token de sessão obrigatório + `hash_equals` em todo POST |
| XSS por conteúdo de projeto | Saída sempre por `e()` (`htmlspecialchars`), CSP sem `unsafe-inline` para script |
| Clickjacking | `X-Frame-Options: SAMEORIGIN` + `frame-ancestors 'self'` |
| Vazamento por `Referer` | `Referrer-Policy: strict-origin-when-cross-origin` |
| Força bruta no formulário público | Limite de pedidos por IP/hora; validação de contato |
| Link de liberação compartilhado | Prazo + `max_usos` + auditoria; escopo restrito a um projeto |
| Injeção de SQL | 100 % prepared statements; nenhum valor concatenado |
| Path traversal em estáticos | Rota de assets rejeita `..`; `readfile` só em `public/assets` |
| Credencial esquecida no repositório | `tools/verificar.php` varre o código por padrões de senha/token/chave |

---

## Cabeçalhos aplicados

```
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
X-Frame-Options: SAMEORIGIN
Permissions-Policy: geolocation=(), microphone=(), camera=()
Content-Security-Policy: default-src 'self'; img-src 'self' data: https://avatars.githubusercontent.com;
    style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; font-src 'self';
    object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'self'
X-Robots-Tag: noindex, nofollow   ← apenas nas rotas administrativas e na API
```

`script-src 'self'` sem `unsafe-inline` é o que garante que um conteúdo injetado em um projeto não
consiga executar script. Os dados entregues ao front ficam em `<script type="application/json">`
com `JSON_HEX_TAG | JSON_HEX_AMP`, lido via `JSON.parse`.

---

## Senhas e usuários

- `password_hash` com `PASSWORD_DEFAULT` (bcrypt/Argon2 conforme a build do PHP) e
  `password_needs_rehash` para upgrade automático no próximo login.
- Mínimo de 10 caracteres, imposto em todos os caminhos (painel, CLI, instalador).
- Nenhuma senha é exibida, exportada, escrita em arquivo ou registrada em log — nem em página de
  erro. O `tools/senha.php` lê do terminal **sem eco** e grava só o hash.
- O primeiro administrador é criado no instalador (web) ou por CLI. Não existe usuário nem senha
  padrão no projeto.
- Trocar a rota do painel: `IN3_ROTA_PAINEL=/algum-caminho` no `.env`.

---

## Visibilidade de repositórios — honestidade por construção

O sistema **não adivinha** se um repositório é público ou privado:

- `publico` — confirmado pela API pública;
- `privado` — confirmado por sincronização autenticada;
- `pendente` — declarado na curadoria e ainda não confirmado (sem token).

Repositórios privados não são visíveis na API pública do GitHub; por isso o sincronizador usa um
token de **leitura** informado no `.env` ou no painel, aplicado apenas durante a chamada. Nenhum
endereço de repositório é inventado, e links só aparecem no público quando a curadoria marca
*expor endereços dos repositórios* **e** o item é público.

---

## Auditoria

| Tabela | Conteúdo |
|---|---|
| `acesso_log` | rota, papel, usuário, termo buscado, nº de resultados, IP, agente |
| `auditoria` | login, criação/edição de projeto, pedido, emissão e uso de acesso, mudança de usuário |
| `tentativas_login` | sucessos e falhas, com limpeza automática após 30 dias |

A **Visão geral** e a seção **Sistema** do painel são as duas janelas para esses registros.

---

## Verificação contínua

`php tools/verificar.php` roda sete blocos de checagem e **sai com código 1** se qualquer um falhar:

1. título de item interno não aparece em HTML público;
2. montagem de camadas de **todos** os projetos, simulando visitante anônimo;
3. busca anônima com termos sensíveis (`LGPD`, `telemetria`, `arquitetura`, `riscos`, `privacidade`…);
4. varredura do código por senha/token/chave em texto claro;
5. conferência de que toda senha de usuário é hash válido;
6. rota do painel ausente de `robots.txt` e do HTML público;
7. API pública (quando há servidor): nenhum item interno, nenhuma camada > 1, nenhum playbook exposto.

Recomendação de operação: **rode o verificador antes de publicar e depois de cada alteração de
curadoria.** Se ele falhar, não publique.

---

## O que ainda depende de você

- **HTTPS.** O sistema detecta e marca o cookie como `Secure` sob HTTPS, mas o certificado é do host.
- **Proxy reverso.** Se houver, configure e só então ligue `IN3_CONFIA_PROXY=1` (usa
  `X-Forwarded-For` para o IP real e para os limites de tentativa).
- **Backup.** `data/in3.db` é o acervo inteiro: faça cópia periódica (é um único arquivo).
- **Proteção do `.env`.** Ele contém o token do GitHub. Fora do repositório, com permissão `600`.
- **Hosts privados.** Sem token, repositórios privados ficam como `pendente` — o que é o
  comportamento correto, mas exige uma sincronização autenticada para virar `privado`.

# 🏗️ Arquitetura & decisões técnicas

## Princípio central

**A camada bloqueada não existe na resposta.** Não é `display:none`, não é campo vazio:
o servidor remove o corpo (`unset`) antes de montar o HTML, e a busca nunca faz `SELECT`
de uma linha acima do escopo. Quem não tem acesso não recebe o texto, nem por engano de CSS.

## Fluxo de uma requisição

```
navegador → public/index.php (controlador frontal)
              ├── cabeçalhos de segurança (CSP, nosniff, no-referrer, DENY)
              ├── /.env + data/in3.db (PDO SQLite, WAL)
              ├── /<rota do painel>/*  → rotas_painel.php   (sessão + RBAC)
              ├── /api/v1/publico/*    → JSON já filtrado
              └── demais rotas         → rotas_publicas.php (clareza do visitante)
```

## Módulos

| arquivo | responsabilidade |
|---|---|
| `src/nucleo.php` | constantes (níveis, papéis, permissões), `.env`, PDO/SQLite, sessão, login, CSRF, auditoria, acervo, camadas, busca, ícones SVG |
| `src/vistas.php` | layout, capa voxel, ASCII, cartões, hotsite, painel (todas as abas) |
| `src/rotas_publicas.php` | `/`, `/p/{slug}`, `/busca`, `/tecnologias`, `/pedido`, `/acesso/{token}`, robots, sitemap, saúde |
| `src/rotas_painel.php` | entrar/sair, senha, projetos, pedidos, usuários, busca interna, sistema, API do painel |
| `tools/*` | instalar, semear, sincronizar GitHub, verificar, senha, exportar, auxiliar Python |

## Modelo de dados (SQLite)

`config` · `usuarios` · `tentativas` · `auditoria` · `repos` · `projetos` ·
`projeto_repos` (N:N) · `camadas` (4 por projeto, `UNIQUE(projeto_id, profundidade)`) ·
`pedidos` · `acessos` (tokens temporários).

Decisões: `WAL` + `busy_timeout` para leitura concorrente durante escritas; `foreign_keys=ON`;
`UNIQUE (projeto_id, profundidade)` faz o *upsert* das camadas ser idempotente; nenhum
conteúdo sensível vive em arquivo versionado — `data/*.db` está no `.gitignore`.

## Clareza (o número que governa tudo)

`clareza_efetiva()` = clareza do usuário (ou 1 para o anônimo), elevada por
`clareza_extra` **apenas quando** `clareza_projeto` casa com o projeto aberto pelo
link `/acesso/TOKEN`. Assim um link liberado para o projeto A não abre o projeto B.

`camadas_do_projeto()` calcula `limite = min(clareza, teto_do_projeto)` e devolve as
quatro camadas já com `corpo` vazio nas bloqueadas.

## Estilo voxel — por que é decoração honesta

A cena é isométrica pura em CSS 3D (`rotateX(58°) rotateZ(-45°)`) — **sem rotação 2D**,
câmera fixa. Cada cubo tem três faces (topo, esquerda, direita) e é um `<a>` navegável
por teclado. Se o CSS 3D não estiver disponível, a lista de cartões logo abaixo continua
completa: **nenhuma informação existe só na cena**. O mesmo vale para o ASCII interativo
e para o parallax — tudo isso é enriquecimento, nunca requisito de leitura.

Sem CDN: nenhuma fonte, script ou imagem vem de fora, então a página não "quebra" offline
e a CSP pode ser estrita (`script-src 'self'`).

## Segurança (resumo — detalhe em SEGURANCA.md)

Sessão com cookie `HttpOnly` + `SameSite=Lax` + `Secure` sob HTTPS; `session_regenerate_id`
no login; bcrypt custo 12; bloqueio de força bruta (8 falhas por IP/usuário em 15 min);
CSRF em **todos** os formulários POST; permissões conferidas no servidor em toda rota;
auditoria de login, publicação, liberação de pedido e mudança de usuário.

## Limitações conhecidas

1. `IN3_ROTA_PAINEL` é obscuridade, não autorização — a proteção real é login + RBAC.
2. Sem `GITHUB_TOKEN`, repositórios privados ficam `pendente`, sem nomes.
3. Não há envio de e-mail: o link do pedido é copiado no painel pelo curador.
4. `php -S` é servidor de desenvolvimento — em produção use Nginx/Apache (ver DEPLOY.md).

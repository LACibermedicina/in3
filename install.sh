#!/usr/bin/env bash
# =====================================================================
# IN³ · INcubator — autoinstalador (Linux / macOS / WSL)
#
#   bash install.sh                 instala e sobe o servidor
#   bash install.sh --so-preparar   só prepara (sem subir servidor)
#   bash install.sh --porta=9000    muda a porta
#
# O que faz, em ordem:
#   1. confere PHP 8.1+ com pdo_sqlite, sqlite3 e mbstring;
#   2. copia .env.example para .env (se ainda não existir);
#   3. cria o banco e aplica o esquema (tools/instalar);
#   4. sincroniza o inventário GitHub (público; privado exige token no .env);
#   5. semeia o acervo a partir da curadoria;
#   6. roda o verificador de privacidade (aborta em falha);
#   7. sobe o servidor embutido do PHP.
# =====================================================================
set -euo pipefail

cd "$(dirname "$0")"
RAIZ="$(pwd)"
PORTA="${PORT:-8787}"
SUBIR=1

for arg in "$@"; do
  case "$arg" in
    --so-preparar) SUBIR=0 ;;
    --porta=*) PORTA="${arg#*=}" ;;
    -h|--help) sed -n '2,20p' "$0"; exit 0 ;;
  esac
done

azul()  { printf '\033[38;5;44m%s\033[0m\n' "$1"; }
ok()    { printf '  \033[38;5;42m✓\033[0m %s\n' "$1"; }
erro()  { printf '  \033[38;5;203m✖\033[0m %s\n' "$1" >&2; }
aviso() { printf '  \033[38;5;221m!\033[0m %s\n' "$1"; }

printf '\n'
azul '╭──────────────────────────────────────────────────────────────╮'
azul '│  IN³ · INcubator — portfólio vivo da incubadora m3d.pro      │'
azul '│  O que entra na m3d, sai ao cubo.                             │'
azul '╰──────────────────────────────────────────────────────────────╯'
printf '\n'

# ---------------------------------------------------------------- 1. PHP
azul '1/7 · Ambiente'
if ! command -v php >/dev/null 2>&1; then
  erro 'PHP não encontrado.'
  cat <<'DICA'
     Instale o PHP 8.1 ou superior:
       Debian/Ubuntu : sudo apt-get update && sudo apt-get install -y php-cli php-sqlite3 php-mbstring
       Fedora/RHEL   : sudo dnf install -y php-cli php-pdo php-mbstring
       Alpine        : sudo apk add php83 php83-pdo_sqlite php83-mbstring php83-sqlite3
       macOS         : brew install php
DICA
  exit 1
fi
PHPV="$(php -r 'echo PHP_VERSION;')"
if [ "$(php -r 'echo version_compare(PHP_VERSION,"8.1.0",">=")?"1":"0";')" != "1" ]; then
  erro "PHP $PHPV é antigo demais (mínimo 8.1)."; exit 1
fi
ok "PHP $PHPV"

for ext in pdo_sqlite sqlite3 mbstring json; do
  if php -m | grep -qi "^${ext}$"; then ok "extensão $ext"; else
    erro "extensão $ext ausente (instale php-${ext//_/-})"; exit 1
  fi
done

if php -r 'try{(new PDO("sqlite::memory:"))->exec("CREATE VIRTUAL TABLE t USING fts5(a)");echo 1;}catch(Exception $e){echo 0;}' | grep -q 1; then
  ok 'FTS5 disponível (busca em índice de texto completo)'
else
  aviso 'FTS5 indisponível — a busca usará LIKE (funciona, apenas mais simples)'
fi

# ------------------------------------------------------------ 2. arquivos
azul '2/7 · Arquivos e permissões'
mkdir -p data data/semear
if [ ! -f .env ]; then
  cp .env.example .env
  ok '.env criado a partir de .env.example'
else
  ok '.env já existia (mantido)'
fi
chmod 600 .env 2>/dev/null || true
for d in data data/semear; do
  if [ ! -w "$d" ]; then erro "$d não é gravável"; exit 1; fi
done
ok 'diretórios de dados graváveis'

# --------------------------------------------------------------- 3. banco
azul '3/7 · Banco de dados'
if php tools/instalar.php >/dev/null 2>&1 || php public/index.php --instalar >/dev/null 2>&1; then
  ok 'esquema aplicado'
else
  erro 'falha ao aplicar o esquema (rode: php tools/instalar.php)'; exit 1
fi

# ---------------------------------------------------------- 4. sincronizar
azul '4/7 · Inventário GitHub'
if php tools/sincronizar.php; then
  ok 'inventário sincronizado'
else
  aviso 'sincronização indisponível (sem rede?) — usando o cache já existente em data/semear/'
fi

# -------------------------------------------------------------- 5. semear
azul '5/7 · Semear acervo'
if php tools/semear.php; then
  ok 'acervo semeado'
else
  erro 'falha ao semear'; exit 1
fi

# ------------------------------------------------------------ 6. verificar
azul '6/7 · Verificação de privacidade'
if php tools/verificar.php; then
  ok 'nenhum vazamento detectado'
else
  erro 'verificação falhou — corrija antes de publicar'; exit 1
fi

# --------------------------------------------------------------- 7. subir
azul '7/7 · Servidor'
cat <<DICA

  Falta criar o primeiro administrador (a senha é digitada sem eco):

      php tools/senha.php --usuario=SEU_USUARIO --criar --papel=admin --clareza=4

  O painel fica na rota definida em IN3_ROTA_PAINEL (.env) — por padrão:
      http://localhost:${PORTA}$(grep -E '^IN3_ROTA_PAINEL=' .env | cut -d= -f2)/entrar

DICA

if [ "$SUBIR" = "0" ]; then
  ok 'preparação concluída (--so-preparar)'
  exit 0
fi
ok "servidor em http://localhost:${PORTA}  (Ctrl+C para parar)"
printf '\n'
exec php -S "0.0.0.0:${PORTA}" -t public public/index.php

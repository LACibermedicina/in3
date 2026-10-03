#!/usr/bin/env bash
# =====================================================================
#  IN³ · INcubator — autoinstalador (Linux · macOS · WSL)
#  Cria o .env, o banco, o acervo e a conta root; sobe o servidor.
#  Uso:  ./install.sh            (interativo)
#        ./install.sh --subir 0  (prepara sem subir o servidor)
# =====================================================================
set -euo pipefail
cd "$(dirname "$0")"

SUBIR=1
for a in "$@"; do
  case "$a" in
    --subir=0|--subir0) SUBIR=0 ;;
    --ajuda|-h) sed -n '2,8p' "$0"; exit 0 ;;
  esac
done

ok(){ printf '  \033[32m✓\033[0m %s\n' "$1"; }
av(){ printf '  \033[33m!\033[0m %s\n' "$1"; }
er(){ printf '  \033[31m✖\033[0m %s\n' "$1"; exit 1; }

printf '\n\033[1m=== IN³ · INcubator — instalação ===\033[0m\n\n'

# ---------------------------------------------------------- requisitos
command -v php >/dev/null 2>&1 || er "PHP não encontrado. Instale PHP 8+ (php-cli, php-sqlite3, php-mbstring)."
PHPV=$(php -r 'echo PHP_VERSION;')
php -r 'exit(version_compare(PHP_VERSION,"8.0.0",">=")?0:1);' || er "PHP $PHPV é antigo: este sistema pede PHP 8 ou superior."
ok "PHP $PHPV"

for ext in pdo_sqlite sqlite3 mbstring; do
  php -m | grep -qi "^${ext}$" || er "extensão PHP ausente: ${ext}"
done
ok "extensões php: pdo_sqlite · sqlite3 · mbstring"

# ---------------------------------------------------------------- .env
if [ -f .env ]; then
  av ".env já existe — mantido como está"
else
  cp .env.example .env
  ALVO=$(printf 'in3-%s' "$(openssl rand -hex 4 2>/dev/null || date +%s)")
  if sed --version >/dev/null 2>&1; then
    sed -i "s|^IN3_ROTA_PAINEL=.*|IN3_ROTA_PAINEL=${ALVO}|" .env
  else
    sed -i '' "s|^IN3_ROTA_PAINEL=.*|IN3_ROTA_PAINEL=${ALVO}|" .env
  fi
  chmod 600 .env
  ok ".env criado (rota do painel: /${ALVO}) — permissão 600"
fi

# -------------------------------------------------------------- pastas
mkdir -p data/semear
chmod 775 data 2>/dev/null || true
ok "pastas de dados prontas"

# ------------------------------------------------------------ instalar
printf '\n'
php tools/instalar.php
printf '\n'
php tools/verificar.php || av "verificação com pendências — leia a saída acima"

if [ "$SUBIR" = "0" ]; then
  printf '\n  Preparação concluída. Suba depois com:\n'
  printf '    php -S 0.0.0.0:%s -t public public/index.php\n\n' "$(grep -E '^PORT=' .env | cut -d= -f2 || echo 8787)"
  exit 0
fi

PORTA=$(grep -E '^PORT=' .env | cut -d= -f2 | tr -d '"' || true)
PORTA=${PORTA:-8787}
ROTA=$(grep -E '^IN3_ROTA_PAINEL=' .env | cut -d= -f2 | tr -d '"' || echo console)
printf '\n  \033[1mSite público:\033[0m http://localhost:%s\n' "$PORTA"
printf '  \033[1mPainel:\033[0m       http://localhost:%s/%s\n' "$PORTA" "$ROTA"
printf '  (Ctrl+C para parar)\n\n'
exec php -S "0.0.0.0:${PORTA}" -t public public/index.php

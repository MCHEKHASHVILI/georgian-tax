#!/usr/bin/env bash
# Development sandbox for these packages where Packagist is unreachable (cloud sessions).
# Builds PHP 8.4 with the extensions the packages need, and points composer at GitHub mirrors.
#
#   source tools/sandbox/setup.sh            # sets PATH, COMPOSER_HOME; builds PHP once (~10 min)
#   python3 tools/sandbox/resolve.py <dir>   # composer update, adding a GitHub repo for anything missing
#
# Not shipped: tools/ is export-ignored.
set -e
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PREFIX="${PHP84_PREFIX:-$HOME/php84}"
export COMPOSER_HOME="${COMPOSER_HOME_SANDBOX:-$HOME/.composer-sandbox}" COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_PROCESS_TIMEOUT=1200

if [ ! -x "$PREFIX/bin/php" ]; then
  export DEBIAN_FRONTEND=noninteractive
  PKGS="libonig-dev libsqlite3-dev libxml2-dev libssl-dev zlib1g-dev libcurl4-openssl-dev pkg-config autoconf bison re2c"
  apt-get install -y -q $PKGS > /tmp/php84-apt.log 2>&1 || { apt-get update -q > /dev/null; apt-get install -y -q $PKGS > /tmp/php84-apt.log 2>&1; }
  TAG=$(git ls-remote --tags https://github.com/php/php-src.git 'php-8.4.*' | grep -Eo 'php-8\.4\.[0-9]+$' | sort -V | tail -1)
  SRC=$(mktemp -d)
  git clone -q --depth 1 --branch "$TAG" https://github.com/php/php-src.git "$SRC"
  (cd "$SRC" && ./buildconf --force > /dev/null 2>&1 && ./configure --prefix="$PREFIX" --disable-cgi \
    --enable-mbstring --with-openssl --with-zlib --with-curl --with-pdo-sqlite --with-sqlite3 \
    --enable-dom --enable-xml --enable-xmlwriter --enable-xmlreader --enable-simplexml --enable-tokenizer \
    --enable-ctype --enable-fileinfo --enable-filter --enable-phar --enable-pcntl --enable-posix \
    --enable-bcmath --enable-session --with-iconv > /tmp/php84-configure.log 2>&1 \
    && make -j"$(nproc)" > /tmp/php84-make.log 2>&1 && make install > /tmp/php84-install.log 2>&1)
  rm -rf "$SRC"
fi
export PATH="$PREFIX/bin:$PATH"

mkdir -p "$COMPOSER_HOME"
if [ ! -f "$COMPOSER_HOME/config.json" ]; then
  # The sibling checkouts (money, countries, tax-legislation next to this repo) win over GitHub,
  # symlinked, so a change in one package is tested in the others before it is pushed.
  python3 - "$HERE/composer-repositories.json" "$COMPOSER_HOME/config.json" "$(cd "$HERE/../../.." && pwd)" <<'PY'
import json, os, sys
repos = json.load(open(sys.argv[1]))['repositories']
local = [{'type': 'path', 'url': os.path.join(sys.argv[3], p), 'options': {'versions': {f'mchekhashvili/{p}': '1.0.x-dev'}, 'symlink': True}}
         for p in ('tax-legislation', 'countries', 'money') if os.path.isdir(os.path.join(sys.argv[3], p))]
json.dump({'repositories': [repos[0], *local, *repos[1:]]}, open(sys.argv[2], 'w'), indent=2)
PY
fi
php -v | head -1

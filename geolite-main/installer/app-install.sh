#!/bin/bash
set -euo pipefail

APP_DB='geolite'
APP_DB_PASS="$(tr -dc '_A-Za-z0-9' </dev/urandom | head -c32)"
ADMIN_APP_PASS='geolite'

WWW_DIR='/var/www/html/geolite'   # default, can be overridden by arg
DATA_DIR='/var/www/data'
WITH_DEMO='false'

HNAME="$(hostname -f)"

touch /root/auth.txt
export DEBIAN_FRONTEND=noninteractive

# Require PostgreSQL client installed (createdb)
if [ ! -f /usr/bin/createdb ]; then
  echo "Error: Missing PG createdb! First run ./installer/postgres.sh"
  exit 1
fi

# Must run from project root where 'installer' exists
if [ ! -d installer ]; then
  echo "Usage: ./installer/app-install.sh [--with-demo] [WWW_DIR]"
  exit 1
fi

# Args:
#   --with-demo   (optional)
#   WWW_DIR       (optional positional)
if [[ $# -ge 1 && "$1" == "--with-demo" ]]; then
  WITH_DEMO='true'
  shift
fi
if [[ $# -ge 1 ]]; then
  WWW_DIR="$1"
fi

# Make directories
mkdir -p "$WWW_DIR"
mkdir -p "$DATA_DIR"

# 1. Install packages (assume PG is preinstalled)
apt-get -y update
apt-get -y install \
  apache2 libapache2-mod-php libapache2-mod-fcgid php-{pgsql,mbstring,xml,zip,gd,curl} \
  gdal-bin curl \
  python3 python3-psycopg2

a2enmod ssl headers expires fcgid cgi rewrite

# Encode app admin password and inject into init.sql
ADMIN_APP_PASS_ENCODED="$(php -r "echo password_hash('${ADMIN_APP_PASS}', PASSWORD_DEFAULT);")"
sed -i.save "s|ADMIN_APP_PASS|${ADMIN_APP_PASS_ENCODED}|" installer/init.sql

# 2. Create DB, user, set password, load schema + init
su postgres -c "createdb ${APP_DB}" || true
su postgres -c "createuser -sd ${APP_DB}" || true
su postgres -c "psql -c \"ALTER USER ${APP_DB} WITH PASSWORD '${APP_DB_PASS}'\""
su postgres -c "psql -c \"ALTER DATABASE ${APP_DB} OWNER TO ${APP_DB}\""
su postgres -c "psql -d ${APP_DB} < $(pwd)/installer/setup.sql"
su postgres -c "psql -d ${APP_DB} < $(pwd)/installer/init.sql"

# 3. App config constants
cat >incl/const.php <<CAT_EOF
<?php
const DB_HOST = "localhost";
const DB_NAME = "${APP_DB}";
const DB_USER = "${APP_DB}";
const DB_PASS = "${APP_DB_PASS}";
const DB_PORT = 5432;
const DB_SCMA = 'public';

const SESS_USR_KEY = 'qgis_user';
const WWW_DIR = '${WWW_DIR}';
const DATA_DIR = "${DATA_DIR}";
CAT_EOF

# Data dirs
mkdir -p "${DATA_DIR}/uploads"
chown -R www-data:www-data "${DATA_DIR}"

# 4. Deploy app
rsync -a --delete --exclude ".git" ./ "${WWW_DIR}/"
chown -R www-data:www-data "${WWW_DIR}"
rm -rf "${WWW_DIR}/installer"

# 5. PHP tuning
PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_INI="/etc/php/${PHP_VER}/apache2/php.ini"
if [[ -f "$PHP_INI" ]]; then
  sed -i.save \
    -e 's/^upload_max_filesize = .*/upload_max_filesize = 10M/' \
    -e 's/^post_max_size = .*/post_max_size = 11M/' \
    "$PHP_INI"
fi

# 6. Apache
a2enmod ssl rewrite
systemctl restart apache2

# 7. Optional demo
if [[ "$WITH_DEMO" == "true" ]]; then
  su postgres -c "psql -d ${APP_DB} < $(pwd)/installer/demo/demo.sql"
  cp -r installer/demo/data/uploads "${DATA_DIR}/" || true
  mkdir -p "${WWW_DIR}/assets"
  cp -r installer/demo/assets/thumbnails "${WWW_DIR}/assets/" || true
  chown -R www-data:www-data "${DATA_DIR}" "${WWW_DIR}"
fi

# save 1Gb of space
apt-get -y clean all

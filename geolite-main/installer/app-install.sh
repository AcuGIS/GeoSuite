#!/bin/bash -e

APP_DB='geolite'
APP_DB_PASS=$(< /dev/urandom tr -dc _A-Za-z0-9 | head -c32);
ADMIN_APP_PASS='geolite';

WWW_DIR='/var/www/html'
DATA_DIR='/var/www/data'
#CACHE_DIR='/var/www/cache'
WITH_DEMO='false'

HNAME=$(hostname -f)

touch /root/auth.txt
export DEBIAN_FRONTEND=noninteractive

if [ ! -f /usr/bin/createdb ]; then
	echo "Error: Missing PG createdb! First run ./installer/postgres.sh"; exit 1;
fi

if [ ! -d installer ]; then
	echo "Usage: ./installer/app-installer.sh"
	exit 1
fi

if [ $# -ge 1 ] && [ "$1" -eq '--with-demo' ]; then
    WITH_DEMO='true'
    shift;
fi

if [ $# -eq 1 ]; then
	WWW_DIR="${1}"
fi
mkdir -p "${WWW_DIR}"

# 1. Install packages (assume PG is preinstalled)
apt-get -y install apache2 libapache2-mod-php libapache2-mod-fcgid php-{pgsql,mbstring,xml,zip,gd,curl} \
    gdal-bin curl \
    python3 python3-psycopg2

a2enmod ssl headers expires fcgid cgi rewrite

ADMIN_APP_PASS_ENCODED=$(php -r "echo password_hash('${ADMIN_APP_PASS}', PASSWORD_DEFAULT);")
sed -i.save "s|ADMIN_APP_PASS|${ADMIN_APP_PASS_ENCODED}|" installer/init.sql

# 2. Create db
su postgres <<CMD_EOF
createdb ${APP_DB}
createuser -sd ${APP_DB}
psql -c "alter user ${APP_DB} with password '${APP_DB_PASS}'"
psql -c "ALTER DATABASE ${APP_DB} OWNER TO ${APP_DB}"

psql -d ${APP_DB} < installer/setup.sql
psql -d ${APP_DB} < installer/init.sql
CMD_EOF

cat >incl/const.php <<CAT_EOF
<?php
const DB_HOST="localhost";
const DB_NAME="${APP_DB}";
const DB_USER="${APP_DB}";
const DB_PASS="${APP_DB_PASS}";
const DB_PORT = 5432;
const DB_SCMA='public';

const SESS_USR_KEY = 'qgis_user';
const WWW_DIR = '${WWW_DIR}';
const DATA_DIR = "${DATA_DIR}";
CAT_EOF

mkdir -p "${DATA_DIR}/uploads"

#mkdir -p "${WWW_DIR}/downloads"
#mkdir -p "${WWW_DIR}/temp"

chown -R www-data:www-data "${DATA_DIR}"

cp -r . ${WWW_DIR}/
chown -R www-data:www-data ${WWW_DIR}
rm -rf ${WWW_DIR}/{installer}

PHP_VER=$(php -version | head -n 1 | cut -f2 -d' ' | cut -f1,2 -d.)

sed -i.save '
s/upload_max_filesize = .*/upload_max_filesize = 10M/
s/post_max_size = .*/post_max_size = 11M/
' /etc/php/${PHP_VER}/apache2/php.ini

a2enmod ssl rewrite
systemctl restart apache2

if [ ${WITH_DEMO} == 'true' ]; then
    su postgres <<CMD_EOF
psql -d ${APP_DB} < installer/demo/demo.sql
CMD_EOF
    cp -r installer/demo/data/uploads      ${DATA_DIR}/
    cp -r installer/demo/assets/thumbnails ${WWW_DIR}/geolite/assets/
fi

# save 1Gb of space
apt-get -y clean all

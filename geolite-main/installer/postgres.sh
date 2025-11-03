#!/bin/bash -e

PG_VER='17'
PG_PASS=$(< /dev/urandom tr -dc _A-Z-a-z-0-9 | head -c32);

function install_postgresql(){
	RELEASE=$(lsb_release -cs)

	#3. Install PostgreSQL
	echo "deb http://apt.postgresql.org/pub/repos/apt/ ${RELEASE}-pgdg main" > /etc/apt/sources.list.d/pgdg.list
	wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | apt-key add -

	apt-get update -y || true

	apt-get install -y postgresql-${PG_VER} postgresql-client-${PG_VER} postgresql-contrib-${PG_VER} \
						python3-postgresql postgresql-plperl-${PG_VER} \
						postgresql-pltcl-${PG_VER} postgresql-${PG_VER}-postgis-3 \
						odbc-postgresql libpostgresql-jdbc-java
	if [ ! -f /usr/lib/postgresql/${PG_VER}/bin/postgres ]; then
		echo "Error: Get PostgreSQL version"; exit 1;
	fi

	ln -sf /usr/lib/postgresql/${PG_VER}/bin/pg_config 	/usr/bin
	ln -sf /var/lib/postgresql/${PG_VER}/main/		 	/var/lib/postgresql
	ln -sf /var/lib/postgresql/${PG_VER}/backups		/var/lib/postgresql

	systemctl start postgresql

	#5. Set postgres Password
	if [ $(grep -m 1 -c 'pg pass' /root/auth.txt) -eq 0 ]; then
		sudo -u postgres psql 2>/dev/null -c "alter user postgres with password '${PG_PASS}'"
		echo "pg pass: ${PG_PASS}" > /root/auth.txt
	fi

	#4. Add Postgre variables to environment
	if [ $(grep -m 1 -c 'PGDATA' /etc/environment) -eq 0 ]; then
		cat >>/etc/environment <<CMD_EOF
PGDATA=/var/lib/postgresql/${PG_VER}/main
CMD_EOF
	fi

	#6. Configure ph_hba.conf
	cat >/etc/postgresql/${PG_VER}/main/pg_hba.conf <<CMD_EOF
local	all all 							trust
host	all all 127.0.0.1	255.255.255.255	trust
host	all all 0.0.0.0/0					scram-sha-256
host	all all ::1/128						scram-sha-256
hostssl all all 127.0.0.1	255.255.255.255	scram-sha-256
hostssl all all 0.0.0.0/0					scram-sha-256
hostssl all all ::1/128						scram-sha-256
CMD_EOF
	sed -i.save "s/.*listen_addresses.*/listen_addresses = '*'/" /etc/postgresql/${PG_VER}/main/postgresql.conf
	sed -i.save "s/.*ssl =.*/ssl = on/" /etc/postgresql/${PG_VER}/main/postgresql.conf

	#10. Create Symlinks for Backward Compatibility from PostgreSQL 9 to PostgreSQL 8
	#ln -sf /usr/pgsql-9.4/bin/pg_config /usr/bin
	mkdir -p /var/lib/pgsql
	ln -sf /var/lib/postgresql/${PG_VER}/main /var/lib/pgsql
	ln -sf /var/lib/postgresql/${PG_VER}/backups /var/lib/pgsql

	#create SSL certificates
	if [ ! -f /var/lib/postgresql/${PG_VER}/main/server.key -o ! -f /var/lib/postgresql/${PG_VER}/main/server.crt ]; then
		SSL_PASS=$(< /dev/urandom tr -dc _A-Z-a-z-0-9 | head -c32);
		if [ $(grep -m 1 -c 'ssl pass' /root/auth.txt) -eq 0 ]; then
			echo "ssl pass: ${SSL_PASS}" >> /root/auth.txt
		else
			sed -i.save "s/ssl pass:.*/ssl pass: ${SSL_PASS}/" /root/auth.txt
		fi
		openssl genrsa -des3 -passout pass:${SSL_PASS} -out server.key 2048
		openssl rsa -in server.key -passin pass:${SSL_PASS} -out server.key

		chmod 400 server.key

		openssl req -new -key server.key -days 3650 -out server.crt -passin pass:${SSL_PASS} -x509 -subj '/C=CA/ST=Frankfurt/L=Frankfurt/O=acuciva-de.com/CN=acuciva-de.com/emailAddress=info@acugis.com'
		chown postgres:postgres server.key server.crt
		mv server.key server.crt /var/lib/postgresql/${PG_VER}/main
	fi

	systemctl restart postgresql
}

touch /root/auth.txt
export DEBIAN_FRONTEND=noninteractive

add-apt-repository -y universe
apt-get -y update || true

apt-get -y install wget unzip

install_postgresql;

#!/bin/bash -e
#For use on clean Ubuntu 22.04 only!!!
#Cited, Inc. Wilmington, Delaware
#Description: GeoSuite Ubuntu installer

# default menu options
WEBMIN_MODS='geoserver postgis certbot'
TOMCAT_MAJOR=9
JAVA_FLAVOR='OpenJDK'
GEOSERVER_WEBAPP='Yes'

#Set application user and database name
#Get hostname

HNAME=$(hostname | sed -n 1p | cut -f1 -d' ' | tr -d '\n')

#Set postgresql version and password (random)

PG_VER='17'
PG_PASS=$(< /dev/urandom tr -dc _A-Z-a-z-0-9 | head -c32);

BUILD_SSL='no'

APP_DB='quartz'
APP_DB_PASS=$(< /dev/urandom tr -dc _A-Za-z0-9 | head -c32);
DATA_DIR='/var/www/data'
CACHE_DIR='/var/www/cache'
APPS_DIR='/var/www/html/apps'

#Create certificate for use by postgres

function make_cert_key(){
  name=$1

  SSL_PASS=$(< /dev/urandom tr -dc _A-Z-a-z-0-9 | head -c32);
  if [ $(grep -m 1 -c "ssl ${name} pass" /root/auth.txt) -eq 0 ]; then
    echo "ssl ${name} pass: ${SSL_PASS}" >> /root/auth.txt
  else
    sed -i.save "s/ssl ${name} pass:.*/ssl ${name} pass: ${SSL_PASS}/" /root/auth.txt
  fi
  openssl genrsa -des3 -passout pass:${SSL_PASS} -out ${name}.key 2048
  openssl rsa -in ${name}.key -passin pass:${SSL_PASS} -out ${name}.key

  chmod 400 ${name}.key

  openssl req -new -key ${name}.key -days 3650 -out ${name}.crt -passin pass:${SSL_PASS} -x509 -subj "/C=CA/ST=Frankfurt/L=Frankfurt/O=${HNAME}/CN=${HNAME}/emailAddress=info@acugis.com"
}

#Install PostgreSQL


function install_postgresql(){
	RELEASE=$(lsb_release -cs)

	echo "deb http://apt.postgresql.org/pub/repos/apt/ ${RELEASE}-pgdg main" > /etc/apt/sources.list.d/pgdg.list
	wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | apt-key add -
	apt-get -y update
	apt-get -y install postgresql-${PG_VER} postgresql-client-${PG_VER} postgresql-contrib-${PG_VER} \
						python3-postgresql postgresql-plperl-${PG_VER} postgresql-plpython3-${PG_VER} \
						postgresql-pltcl-${PG_VER} postgresql-${PG_VER}-postgis-3 \
						odbc-postgresql libpostgresql-jdbc-java
	if [ ! -f /usr/lib/postgresql/${PG_VER}/bin/postgres ]; then
		echo "Error: Get PostgreSQL version"; exit 1;
	fi

	ln -sf /usr/lib/postgresql/${PG_VER}/bin/pg_config 	/usr/bin
	ln -sf /var/lib/postgresql/${PG_VER}/main/		 	/var/lib/postgresql
	ln -sf /var/lib/postgresql/${PG_VER}/backups		/var/lib/postgresql

	service postgresql start

#Set postgres Password
	if [ $(grep -m 1 -c 'pg pass' /root/auth.txt) -eq 0 ]; then
		sudo -u postgres psql 2>/dev/null -c "alter user postgres with password '${PG_PASS}'"
		echo "pg pass: ${PG_PASS}" >> /root/auth.txt
	fi

#Add Postgre variables to environment
	if [ $(grep -m 1 -c 'PGDATA' /etc/environment) -eq 0 ]; then
		cat >>/etc/environment <<CMD_EOF
export PGDATA=/var/lib/postgresql/${PG_VER}/main
CMD_EOF
	fi

#Config pg_hba.conf

	cat >/etc/postgresql/${PG_VER}/main/pg_hba.conf <<CMD_EOF
local	all all 				trust
host	all all 127.0.0.1	255.255.255.255	trust
host	all all 0.0.0.0/0			scram-sha-256
host	all all ::1/128				scram-sha-256
hostssl all all 127.0.0.1	255.255.255.255	scram-sha-256
hostssl all all 0.0.0.0/0			scram-sha-256
hostssl all all ::1/128				scram-sha-256
CMD_EOF
	sed -i.save "s/.*listen_addresses.*/listen_addresses = '*'/" /etc/postgresql/${PG_VER}/main/postgresql.conf
	sed -i.save "s/.*ssl =.*/ssl = on/" /etc/postgresql/${PG_VER}/main/postgresql.conf

#Create Symlinks for Backward Compatibility from PostgreSQL 9 to PostgreSQL 8

	mkdir -p /var/lib/pgsql
	ln -sf /var/lib/postgresql/${PG_VER}/main /var/lib/pgsql
	ln -sf /var/lib/postgresql/${PG_VER}/backups /var/lib/pgsql

#Create SSL certificates for postgresql

	if [ ! -f /var/lib/postgresql/${PG_VER}/main/server.key -o ! -f /var/lib/postgresql/${PG_VER}/main/server.crt ]; then
		make_cert_key 'server'
    chown postgres.postgres server.key server.crt
		mv server.key server.crt /var/lib/postgresql/${PG_VER}/main
	fi

	service postgresql restart
}

function info_for_user()

{

#End message for user

echo -e "Installation is now completed."
	
	if [ ${BUILD_SSL} == 'yes' ]; then
		if [ ! -f /etc/letsencrypt/live/${HNAME}/privkey.pem ]; then
			echo 'SSL Provisioning failed.  Please see geosuite.docs.acugis.com for troubleshooting tips.'
		else
			echo 'SSL Provisioning Success.'
		fi
	fi
}

function install_bootstrap_app(){
	if [ ! -d /tmp/GeoSuite-master ]; then
		wget --quiet -P/tmp https://github.com/AcuGIS/GeoSuite/archive/refs/heads/master.zip
		unzip /tmp/master.zip -d/tmp
	fi

 	cp -r /tmp/GeoSuite-master/app/* /var/www/html/
	chown -R www-data:www-data /var/www/html
	mv /tmp/GeoSuite-master/app/data /opt/

	rm -rf /tmp/master.zip
	
	#update app
	find /var/www/html -type f -not -path "/var/www/html/latest/*" -name "*.html" -exec sed -i.save "s/MYLOCALHOST/${HNAME}/g" {} \;
	
}


function install_postgis_pkgs(){
  apt-get install -y postgis postgresql-${PG_VER}-pgrouting-scripts postgresql-${PG_VER}-pgrouting osm2pgsql osm2pgrouting
}


function install_webmin(){
  echo "deb http://download.webmin.com/download/repository sarge contrib" > /etc/apt/sources.list.d/webmin.list
  wget --quiet -qO - http://www.webmin.com/jcameron-key.asc | apt-key add -
  apt-get -y update
  apt-get -y install webmin
	
	mkdir -p /etc/webmin/authentic-theme
	cp -r /var/www/html/portal/*  /etc/webmin/authentic-theme
}

function install_geoserver_module(){

	pushd /tmp/GeoSuite-master/modules/
  
    tar -czf /tmp/geoserver.wbm.gz geoserver
		rm -rf geoserver

    /usr/share/webmin/install-module.pl /tmp/geoserver.wbm.gz
		rm -rf /tmp/geoserver.wbm.gz
  popd

	cat >>/etc/apache2/conf-enabled/geoserver.conf <<EOF
<Location /geoserver>
	ProxyPass http://localhost:8080/geoserver
	ProxyPassReverse http://localhost:8080/geoserver
	ProxyAddHeaders On
	ProxyPreserveHost On
</Location>
EOF
  
	echo -e "repo_ver=${PG_VER}\n" > /etc/webmin/geoserver/repo_ver.txt
}



function install_postgis_module(){

	pushd /tmp/GeoSuite-master/modules/
    rm -f postgis/setup.cgi
    tar -czf /tmp/postgis.wbm.gz postgis
		rm -rf postgis

    /usr/share/webmin/install-module.pl /tmp/postgis.wbm.gz
		rm -rf /tmp/postgis.wbm.gz
  popd
  
	echo -e "repo_ver=${PG_VER}\n" > /etc/webmin/postgis/repo_ver.txt
}

function install_certbot_module(){

	apt-get -y install python3-certbot-apache

	a2enmod ssl
	a2ensite default-ssl

	systemctl restart apache2

  pushd /opt/
    wget --quiet -P/tmp https://github.com/cited/Certbot-Webmin-Module/archive/master.zip
    unzip /tmp/master.zip
    mv Certbot-Webmin-Module-master certbot
    tar -czf /tmp/certbot.wbm.gz certbot
    rm -rf certbot /tmp/master.zip

    /usr/share/webmin/install-module.pl /tmp/certbot.wbm.gz
		rm -rf /tmp/certbot.wbm.gz
  popd
}

function install_tomcat(){

	apt-get -y install haveged

	if [ ! -d /home/tomcat ]; then
		useradd -m tomcat
	fi
	

	if [ ! -d apache-tomcat-${TOMCAT_VER} ]; then
		if [ ! -f /tmp/apache-tomcat-${TOMCAT_VER}.tar.gz ]; then
			wget -q -P/tmp "https://archive.apache.org/dist/tomcat/tomcat-${TOMCAT_MAJOR}/v${TOMCAT_VER}/bin/apache-tomcat-${TOMCAT_VER}.tar.gz"
		fi
		
		pushd /home/tomcat
			tar xzf /tmp/apache-tomcat-${TOMCAT_VER}.tar.gz
			chown -R tomcat:tomcat apache-tomcat-${TOMCAT_VER}
			rm -rf /tmp/apache-tomcat-${TOMCAT_VER}.tar.gz
		popd
	fi

	if [ $(grep -m 1 -c CATALINA_HOME /etc/environment) -eq 0 ]; then
		cat >>/etc/environment <<EOF
CATALINA_HOME=/home/tomcat/apache-tomcat-${TOMCAT_VER}
CATALINA_BASE=/home/tomcat/apache-tomcat-${TOMCAT_VER}
EOF
	fi

	TOMCAT_MANAGER_PASS=$(< /dev/urandom tr -dc _A-Z-a-z-0-9 | head -c32);
	TOMCAT_ADMIN_PASS=$(< /dev/urandom tr -dc _A-Z-a-z-0-9 | head -c32);

	if [ $(grep -m 1 -c 'tomcat manager pass' /root/auth.txt) -eq 0 ]; then
		echo "tomcat manager pass: ${TOMCAT_MANAGER_PASS}" >> /root/auth.txt
	else
		sed -i.save "s/tomcat manager pass: .*/tomcat manager pass: ${TOMCAT_MANAGER_PASS}/" /root/auth.txt
	fi

	if [ $(grep -m 1 -c 'tomcat admin pass' /root/auth.txt) -eq 0 ]; then
		echo "tomcat admin pass: ${TOMCAT_ADMIN_PASS}" >> /root/auth.txt
	else
		sed -i.save "s/tomcat admin pass: .*/tomcat admin pass: ${TOMCAT_ADMIN_PASS}/" /root/auth.txt
	fi

	cat >/home/tomcat/apache-tomcat-${TOMCAT_VER}/conf/tomcat-users.xml <<EOF
<?xml version='1.0' encoding='utf-8'?>
<tomcat-users>
<role rolename="manager-gui" />
<user username="manager" password="${TOMCAT_MANAGER_PASS}" roles="manager-gui" />

<role rolename="admin-gui" />
<user username="admin" password="${TOMCAT_ADMIN_PASS}" roles="manager-gui,admin-gui" />
</tomcat-users>
EOF

	#folder is created after tomcat is started, but we need it now
	mkdir -p /home/tomcat/apache-tomcat-${TOMCAT_VER}/conf/Catalina/localhost/
	cat >/home/tomcat/apache-tomcat-${TOMCAT_VER}/conf/Catalina/localhost/manager.xml <<EOF
<Context privileged="true" antiResourceLocking="false" docBase="\${catalina.home}/webapps/manager">
	<Valve className="org.apache.catalina.valves.RemoteAddrValve" allow="^.*\$" />
</Context>
EOF

	chown -R tomcat:tomcat /home/tomcat

	cat >>"${CATALINA_HOME}/bin/setenv.sh" <<CMD_EOF
CATALINA_PID="${CATALINA_HOME}/temp/tomcat.pid"
JAVA_OPTS="\${JAVA_OPTS} -server -Djava.awt.headless=true -Dorg.geotools.shapefile.datetime=false -XX:+UseParallelGC -XX:ParallelGCThreads=4 -Dfile.encoding=UTF8 -Duser.timezone=UTC -Djavax.servlet.request.encoding=UTF-8 -Djavax.servlet.response.encoding=UTF-8 -DGEOSERVER_CSRF_DISABLED=true -DPRINT_BASE_URL=http://localhost:8080/geoserver/pdf -Dgwc.context.suffix=gwc"
CMD_EOF

	cat >/etc/systemd/system/tomcat.service <<EOF
[Unit]
Description=Tomcat ${TOMCAT_VER}
After=multi-user.target

[Service]
User=tomcat
Group=tomcat

WorkingDirectory=${CATALINA_HOME}
Type=forking
Restart=always

EnvironmentFile=/etc/environment

ExecStart=$CATALINA_HOME/bin/startup.sh
ExecStop=$CATALINA_HOME/bin/shutdown.sh 60 -force

[Install]
WantedBy=multi-user.target
EOF

	systemctl daemon-reload
	
	systemctl enable tomcat
	systemctl start tomcat
}

function install_geoserver(){

	if [ ! -f /tmp/geoserver-${GEO_VER}-war.zip ]; then
		wget -P/tmp http://sourceforge.net/projects/geoserver/files/GeoServer/${GEO_VER}/geoserver-${GEO_VER}-war.zip
	fi

	unzip -ou /tmp/geoserver-${GEO_VER}-war.zip -d/tmp/
	mv /tmp/geoserver.war ${CATALINA_HOME}/webapps/
	chown -R tomcat:tomcat ${CATALINA_HOME}/webapps/geoserver.war
	rm -f /tmp/geoserver-${GEO_VER}-war.zip
	
	a2enmod proxy proxy_http
	
	service tomcat restart
	while [ ! -f ${CATALINA_HOME}/webapps/geoserver/WEB-INF/web.xml ]; do
		sleep 1
	done
	
	sed -i.save '/<\/web-app>/d' ${CATALINA_HOME}/webapps/geoserver/WEB-INF/web.xml
	cat >>${CATALINA_HOME}/webapps/geoserver/WEB-INF/web.xml <<CAT_EOF
<context-param>
      <param-name>PROXY_BASE_URL</param-name>
			<param-value>https://${HNAME}/geoserver</param-value>
</context-param>
</web-app>
CAT_EOF

	service tomcat restart
}

function install_java(){
	if [ "${JAVA_FLAVOR}" == 'OpenJDK' ]; then
		apt-get -y install default-jdk-headless default-jre-headless
	else
		apt-get -y install default-jdk-headless default-jre-headless;
	fi
}

function menu(){
	# disable error flag
	set +e
	
	if [ ! -f /usr/bin/whiptail ]; then
		apt-get install -y whiptail
	fi

	SUITE_FLAVOR=$(whiptail --title "GeoSuite Installer" --menu \
									"Select the GeoSuite version you want to install:" 20 78 4 \
									"GeoSuite Standalone" " " \
									"GeoSuite with QuartzMap" " " 3>&1 1>&2 2>&3)
	
	exitstatus=$?
	if [ $exitstatus != 0 ]; then
		echo "GeoSuite installation cancelled."
		exit 1
	fi
	
	# set options based on flavor we have
	case ${SUITE_FLAVOR} in
		"GeoSuite Standalone")
			;;
		"GeoSuite with QuartzMap")
			STEPS+=("Installing QGIS Server" "Installing QuartzMap")
			CMDS+=("install_qgis_server" "install_quartzmap")
			;;
	esac

	whiptail --title "Hostname is $(hostname -f)" --yesno \
		--yes-button "Continue" --no-button "Quit" \
		"Be sure to set the hostname if you wish to use SSL" 8 78
	
	exitstatus=$?
	if [ $exitstatus != 0 ]; then
	    exit 0
	fi

	whiptail --title "GeoSuite can provision SSL for ${HNAME}" --yesno \
		"Provision SSL for  ${HNAME}?" 8 78
	
	exitstatus=$?
	if [ $exitstatus == 0 ]; then
			BUILD_SSL='yes'
			STEPS+=("Provisioning SSL")
			CMDS+=('provision_ssl')
	fi
	
	# enable error flag
	set -e
	
	echo "Begining installation:"
	echo -e "\tSuite Version: ${SUITE_FLAVOR}"
	echo -e "\tControl Panel Modules: ${WEBMIN_MODS}"
	echo -e "\tTomcat Version: ${TOMCAT_MAJOR}"
	echo -e "\tJava Version: ${JAVA_FLAVOR}"
}

function install_deps(){
	touch /root/auth.txt

	export DEBIAN_FRONTEND=noninteractive
	apt-add-repository -y universe

	apt-get -y install wget unzip apache2 bzip2 rename php libapache2-mod-php php-pgsql
		
	sed -i.save "/<VirtualHost /a\ ServerName ${HNAME}" /etc/apache2/sites-available/default-ssl.conf
	
	# Get Tomcat latest version and set CATALINA_HOME
	TOMCAT_VER=$(wget -q -O- --no-check-certificate https://archive.apache.org/dist/tomcat/tomcat-${TOMCAT_MAJOR}/ | sed -n "s|.*<a href=\"v\(${TOMCAT_MAJOR}\.[0-9.]\+\)/\">v.*|\1|p" | sort -V | tail -n 1)
	if [ -z "${TOMCAT_VER}" ]; then
		echo "Error: Failed to get tomcat version"; exit 1;
	fi
	CATALINA_HOME="/home/tomcat/apache-tomcat-${TOMCAT_VER}"

	GEO_VER=$(wget http://geoserver.org/release/stable/ -O- 2>/dev/null | sed -n 's/^[ \t]\+<h1>GeoServer \(.*\)<\/h1>.*/\1/p')
}

function whiptail_gauge(){
  local MAX_STEPS=${#STEPS[@]}
	let STEP_PERC=100/MAX_STEPS
	local perc=0

  for step in "${!STEPS[@]}"; do
    echo "XXX"
		echo $perc
    echo "${STEPS[step]}\\n"
    echo "XXX"

    ${CMDS[$step]} 1>"/tmp/${CMDS[$step]}.log" 2>&1

    let perc=perc+STEP_PERC || true
		echo "$step" > /tmp/step
  done | whiptail --gauge "Please wait while install completes..." 6 50 0
	
	step=$(cat /tmp/step)
	if [ $step != $((MAX_STEPS-1)) ]; then
		echo "Installation failed at step $step. Check /tmp/${CMDS[$step]}.log for errors!"
		exit 1
	fi
}

function provision_ssl(){
	
	certbot -n --apache --agree-tos --email hostmaster@${HNAME} --no-eff-email -d ${HNAME}
	
	cat /etc/letsencrypt/live/${HNAME}/cert.pem > /etc/webmin/miniserv.pem
	cat /etc/letsencrypt/live/${HNAME}/privkey.pem >> /etc/webmin/miniserv.pem
	echo "extracas=/etc/letsencrypt/live/${HNAME}/fullchain.pem" >> /etc/webmin/miniserv.conf
	
	systemctl restart webmin apache2
}

function install_qgis_server(){
  RELEASE=$(lsb_release -cs)

	#3.4.x Madeira LTR
  echo "deb [trusted=yes] https://qgis.org/ubuntu-ltr/ ${RELEASE} main" > /etc/apt/sources.list.d/qgis.list

  wget -qO - https://qgis.org/downloads/qgis-2021.gpg.key | gpg --no-default-keyring --keyring gnupg-ring:/etc/apt/trusted.gpg.d/qgis-archive.gpg --import
  chmod a+r /etc/apt/trusted.gpg.d/qgis-archive.gpg

	apt-get update -y || true
  apt-get install -y qgis-server python-qgis
	
	if [ -d /etc/logrotate.d ]; then
		cat >/etc/logrotate.d/qgisserver <<CAT_EOF
/var/log/qgisserver.log {
	su www-data www-data
	size 100M
	notifempty
	missingok
	rotate 3
	daily
	compress
	create 660 www-data www-data
}
CAT_EOF
	fi
	
	mkdir -p ${DATA_DIR}/qgis
	chown www-data:www-data ${DATA_DIR}/qgis
	
	touch /var/log/qgisserver.log
	chown www-data:www-data /var/log/qgisserver.log
}

function install_quartzmap(){
	touch /root/auth.txt
	export DEBIAN_FRONTEND=noninteractive
	RELEASE=$(lsb_release -cs)
	
	wget -P/tmp https://github.com/AcuGIS/quartzmap-web-client/archive/refs/heads/main.zip
	unzip /tmp/main.zip
	rm -f /tmp/main.zip

	pushd quartzmap-web-client-main/

  apt-get -y install apache2 php-{pgsql,zip,gd,simplexml,curl,fpm} \
		proftpd libapache2-mod-fcgid postfix python3-certbot-apache gdal-bin \
		r-base r-base-dev r-cran-{raster,htmlwidgets,plotly,rnaturalearthdata,rjson} \
		texlive-latex-base texlive-latex-recommended texlive-xetex cron

	apt-get -y install --no-install-suggests --no-install-recommends texlive-latex-extra
	
	if [ "${RELEASE}" == 'jammy' ]; then
		R --no-save <<R_EOF
install.packages( c('skimr'))
R_EOF
	else
		apt-get -y install r-cran-skimr
	fi

	# compile leaflet package from CRAN
	R --no-save <<R_EOF
install.packages( c('leaflet', 'leaflet.extras', 'rpostgis', 'R3port', 'rnaturalearth'))
R_EOF


	
	# 1. Install packages (assume PG is preinstalled)
	# apt-get -y install apache2 php-{pgsql,zip,gd,simplexml,curl,fpm} \
		# proftpd libapache2-mod-fcgid postfix python3-certbot-apache gdal-bin

	# setup apache
	a2enmod ssl headers expires fcgid cgi
	
	sed 's|<Directory "/">|<Directory "/quartzmap">|
s/DirectoryIndex index.php/DirectoryIndex index.php index.html/
s|<Directory "/var/www/html/|<Directory "/var/www/html/quartzmap/|' < installer/apache2.conf > /etc/apache2/sites-available/default-ssl.conf

	sed "s|\$DATA_DIR|$DATA_DIR|" < installer/qgis_apache2.conf > /etc/apache2/sites-available/qgis.conf

	for f in default-ssl 000-default; do
		sed -i.save "s/#ServerName example.com/ServerName ${HNAME}/" /etc/apache2/sites-available/${f}.conf
	done

	a2ensite 000-default default-ssl qgis
	a2disconf serve-cgi-bin

	# switch to mpm_event to server faster and use HTTP2
	PHP_VER=$(php -version | head -n 1 | cut -f2 -d' ' | cut -f1,2 -d.)
	a2enmod proxy_fcgi setenvif http2
	a2enconf php${PHP_VER}-fpm
	a2dismod php${PHP_VER}
	a2dismod mpm_prefork
	a2enmod mpm_event

	systemctl reload apache2

	#certbot --apache --agree-tos --email hostmaster@${HNAME} --no-eff-email -d ${HNAME}

 	sed -i.save '
s/#DefaultRoot~/DefaultRoot ~/
s/# RequireValidShelloff/RequireValidShell off/' /etc/proftpd/proftpd.conf

	
	systemctl enable proftpd
	systemctl restart proftpd

	# 2. Create db
	su postgres <<CMD_EOF
	createdb ${APP_DB}
	createuser -sd ${APP_DB}
	psql -c "alter user ${APP_DB} with password '${APP_DB_PASS}'"
	psql -c "ALTER DATABASE ${APP_DB} OWNER TO ${APP_DB}"
CMD_EOF

	echo "${APP_DB} pass: ${APP_DB_PASS}" >> /root/auth.txt

	mkdir -p "${APPS_DIR}"
	mkdir -p "${CACHE_DIR}"
	mkdir -p "${DATA_DIR}"

	chown -R www-data:www-data "${APPS_DIR}"
	chown -R www-data:www-data "${CACHE_DIR}"
	chown -R www-data:www-data "${DATA_DIR}"

	# sync service needs +w to apps/1/images dir
	chmod -R g+w "${APPS_DIR}"

	cat >admin/incl/const.php <<CAT_EOF
	<?php
	define("DB_HOST", "localhost");
	define("DB_NAME", "${APP_DB}");
	define("DB_USER", "${APP_DB}");
	define("DB_PASS", "${APP_DB_PASS}");
	define("DB_PORT", 5432);
	define("DB_SCMA", 'public');
	define("APPS_DIR", "${APPS_DIR}");
	define("CACHE_DIR", "${CACHE_DIR}");
	define("DATA_DIR", "${DATA_DIR}");
	define("SUPER_ADMIN_ID", 1);
	define("SESS_USR_KEY", 'quartz_user');
	?>
CAT_EOF

	cp -r . /var/www/html/quartzmap
	chown -R www-data:www-data /var/www/html
	rm -rf /var/www/html/quartzmap/installer

	systemctl restart apache2

	# create group for all FTP users
	groupadd qatusers

	# install ftp user creation script
	for f in create_ftp_user delete_ftp_user update_ftp_user; do
		cp installer/${f}.sh /usr/local/bin/
		chown www-data:www-data /usr/local/bin/${f}.sh
		chmod 0550 /usr/local/bin/${f}.sh
	done

	cat >/etc/sudoers.d/q2w <<CAT_EOF
	www-data ALL = NOPASSWD: /usr/local/bin/create_ftp_user.sh, /usr/local/bin/delete_ftp_user.sh, /usr/local/bin/update_ftp_user.sh
CAT_EOF
	popd
	
	rm -rf quartzmap-web-client-main/
}

################################################################################

declare -x STEPS=(
  'Checking Requirements...'
  'Installing Demo Data....'
	'Installing Libraries....'
	'Installing LeafletJS Apps...'
	'Setting Up Users...'
	'Installing PostgreSQL Repository....'
	'Installing PostGIS Packages....'
	'Installing Java....'
)
declare -x CMDS=(
	'install_deps'
	'install_bootstrap_app'
	'install_postgresql'
	'install_postgis_pkgs'
	'install_java'
)

if [ "${GEOSERVER_WEBAPP}" == 'Yes' ]; then
	STEPS+=("Installing Apache Tomcat...." "Configure Geoserver WAR....")
	CMDS+=("install_tomcat" "install_geoserver")
fi

STEPS+=("Installing Webmin...")
CMDS+=("install_webmin")


for mod in ${WEBMIN_MODS}; do
	mod=$(echo ${mod} | sed 's/"//g')
	STEPS+=("${mod} module")
	CMDS+=("install_${mod}_module")
done

# -------------------- #
menu;
whiptail_gauge;
info_for_user

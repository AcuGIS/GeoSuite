#!/bin/bash -e
#For use on clean Rocky Linux 9 only!!!
#Cited, Inc. Wilmington, Delaware
#Description: GeoHelm Rocky Linux installer

# default menu options
WEBMIN_MODS='geoserver postgis certbot'
TOMCAT_MAJOR=9
JAVA_FLAVOR='OpenJDK'
GEOSERVER_WEBAPP='Yes'

#Set application user and database name
APPUSER='pgis'
APPDB='postgisftw'
APPUSER_PG_PASS=$(< /dev/urandom tr -dc _A-Z-a-z-0-9 | head -c32);

#Get hostname

HNAME=$(hostname | sed -n 1p | cut -f1 -d' ' | tr -d '\n')

#Set postgresql version and password (random)

PG_VER='15'
PG_PASS=$(< /dev/urandom tr -dc _A-Z-a-z-0-9 | head -c32);

BUILD_SSL='no'

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


function disable_pg_versions(){
	# disable other PG versions repos
	dnf config-manager --set-disabled pgdg*
	dnf config-manager --set-enabled pgdg${PG_VER} pgdg-common
}

#Install PostgreSQL
function install_postgresql(){
	#1. Install PostgreSQL repo
	PG_V2=$(echo ${PG_VER} | sed 's/\.//')
	if [ ! -f /etc/yum.repos.d/pgdg-redhat-all.repo ]; then
		rpm -ivh https://download.postgresql.org/pub/repos/yum/reporpms/EL-8-x86_64/pgdg-redhat-repo-latest.noarch.rpm
	fi

	#2. Disable CentOS repo for PostgreSQL
	if [ $(grep -m 1 -c 'exclude=postgresql' /etc/yum.repos.d/CentOS-Base.repo) -eq 0 ]; then
		sed -i.save '/\[base\]/a\exclude=postgresql*' /etc/yum.repos.d/CentOS-Base.repo
		sed -i.save '/\[updates\]/a\exclude=postgresql*' /etc/yum.repos.d/CentOS-Base.repo
	fi

	#3. Install PostgreSQL
	# postgresql${PG_V2}-devel*
	dnf install -y postgresql${PG_V2} postgresql${PG_VER}-devel postgresql${PG_V2}-server postgresql${PG_V2}-libs postgresql${PG_V2}-contrib postgresql${PG_V2}-plperl postgresql${PG_V2}-plpython3 postgresql${PG_V2}-pltcl postgresql${PG_V2}-odbc

	export PGDATA='/var/lib/pgsql/${PG_VER}/data'
	export PATH="${PATH}:/usr/pgsql-${PG_VER}/bin/"
	if [ $(grep -m 1 -c '/usr/pgsql-${PG_VER}/bin/' /etc/environment) -eq 0 ]; then
		echo "PATH=${PATH}" >> /etc/environment
	fi

	if [ $(grep -m 1 -c 'PGDATA' /etc/environment) -eq 0 ]; then
		echo "PGDATA=${PGDATA}" >> /etc/environment
	fi

	if [ ! -f /var/lib/pgsql/${PG_VER}/data/pg_hba.conf ]; then
		sudo -u postgres /usr/pgsql-${PG_VER}/bin/initdb -D /var/lib/pgsql/${PG_VER}/data
	fi

	systemctl start postgresql-${PG_VER}

	#5. Set postgres Password
	if [ $(grep -m 1 -c 'pg pass' /root/auth.txt) -eq 0 ]; then
		sudo -u postgres psql 2>/dev/null -c "alter user postgres with password '${PG_PASS}'"
		echo "pg pass: ${PG_PASS}" > /root/auth.txt
	fi

	#6. Configure ph_hba.conf
	cat >/var/lib/pgsql/${PG_VER}/data/pg_hba.conf <<CMD_EOF
local	all all 							trust
host	all all 127.0.0.1	255.255.255.255	scram-sha-256
host	all all 0.0.0.0/0					scram-sha-256
host	all all ::1/128						scram-sha-256
hostssl all all 127.0.0.1	255.255.255.255	scram-sha-256
hostssl all all 0.0.0.0/0					scram-sha-256
hostssl all all ::1/128						scram-sha-256
CMD_EOF
	sed -i.save "s/.*listen_addresses.*/listen_addresses = '*'/" /var/lib/pgsql/${PG_VER}/data/postgresql.conf
	sed -i.save "s/.*ssl =.*/ssl = on/" /var/lib/pgsql/${PG_VER}/data/postgresql.conf

	#10. Create Symlinks for Backward Compatibility from PostgreSQL 9 to PostgreSQL 8
	ln -sf /usr/pgsql-${PG_VER}/bin/pg_config /usr/bin
	ln -sf /var/lib/pgsql/${PG_VER}/data /var/lib/pgsql
	ln -sf /var/lib/pgsql/${PG_VER}/backups /var/lib/pgsql

	#create SSL certificates
	if [ ! -f /var/lib/pgsql/${PG_VER}/data/server.key -o ! -f /var/lib/pgsql/${PG_VER}/data/server.crt ]; then
		make_cert_key 'server'
    chown postgres.postgres server.key server.crt
		mv server.key server.crt /var/lib/pgsql/${PG_VER}/data
	fi

	systemctl restart postgresql-${PG_VER}
	systemctl enable postgresql-${PG_VER}
	
	disable_pg_versions
}

#Set up postgresql for Crunchy Data stuff

function crunchy_setup_pg(){

  dnf install -y postgis python3

  sudo -u postgres createuser ${APPUSER} --superuser

  sudo -u postgres psql <<CMD_EOF
alter user ${APPUSER} with password '${APPUSER_PG_PASS}';
CREATE DATABASE ${APPDB} WITH OWNER = ${APPUSER} ENCODING = 'UTF8';
\connect ${APPDB};
CREATE SCHEMA ${APPDB};
CREATE EXTENSION postgis;
CREATE EXTENSION pgrouting;
CMD_EOF

  echo "${APPUSER} PG pass: ${APPUSER_PG_PASS}" >> /root/auth.txt
}

#Load Natual Earth data for testing


function load_pg_data(){
  pushd /home/pgis
    wget --quiet https://www.naturalearthdata.com/http//www.naturalearthdata.com/download/50m/cultural/ne_50m_admin_0_countries.zip
    unzip ne_50m_admin_0_countries.zip
    rm -f ne_50m_admin_0_countries.zip

    chown pgis:pgis ne_50m_admin_0_countries.*

    shp2pgsql -I -s 4326 -W "latin1" ne_50m_admin_0_countries.shp countries | sudo -u ${APPUSER} psql -d ${APPDB}

   
    #load routing data
    wget --quiet http://download.osgeo.org/livedvd/data/osm/Boston_MA/Boston_MA.osm.bz2
    bunzip2 Boston_MA.osm.bz2
    osm2pgrouting --username ${APPUSER} --password ${APPUSER_PG_PASS} --host 127.0.0.1 --dbname ${APPDB} --file Boston_MA.osm
    rm -f Boston_MA.osm
  popd
}

#Install pg_tileserv and config to run as a service


function install_pg_tileserv(){
  TILESERV_HOME='/opt/pg_tileserv'
  mkdir -p ${TILESERV_HOME}

  pushd ${TILESERV_HOME}
    wget --quiet -P/tmp https://postgisftw.s3.amazonaws.com/pg_tileserv_latest_linux.zip
    unzip /tmp/pg_tileserv_latest_linux.zip
    rm -f /tmp/pg_tileserv_latest_linux.zip

    pushd config
     	sed -i.save "s|# DbConnection = \"postgresql://username:password@host/dbname\"|DbConnection = \"postgresql://${APPUSER}:${APPUSER_PG_PASS}@localhost/${APPDB}\"|" pg_tileserv.toml.example
  	  sed -i.save "s|^AssetsPath =.*|AssetsPath = \"${TILESERV_HOME}/assets\"|g" pg_tileserv.toml.example
      sed -i.save 's/^[# ]*HttpPort = .*/HttpPort = 7800/' pg_tileserv.toml.example
      sed -i.save 's/^[# ]*CacheTTL = .*/CacheTTL = 600/' pg_tileserv.toml.example
      sed -i.save 's/^HttpsPort =/#HttpsPort =/' pg_tileserv.toml.example
     	mv pg_tileserv.toml.example pg_tileserv.toml
    popd
  popd



  chown -R ${APPUSER}:${APPUSER} ${TILESERV_HOME}

#The service file

  cat >/etc/systemd/system/pg_tileserv.service <<CMD_EOF
[Unit]
Description=PG TileServ
After=multi-user.target

[Service]
User=${APPUSER}
WorkingDirectory=${TILESERV_HOME}
Type=simple
Restart=always
ExecStart=${TILESERV_HOME}/pg_tileserv --config ${TILESERV_HOME}/config/pg_tileserv.toml



[Install]
WantedBy=multi-user.target
CMD_EOF

  systemctl daemon-reload
  systemctl enable pg_tileserv
  systemctl start pg_tileserv
}

#Install pg_featureserv and config to run as a service

function install_pg_featureserv(){
  FEATSERV_HOME='/opt/pg_featureserv'
  mkdir -p ${FEATSERV_HOME}

  pushd ${FEATSERV_HOME}
    wget --quiet -P/tmp https://postgisftw.s3.amazonaws.com/pg_featureserv_latest_linux.zip
    unzip /tmp/pg_featureserv_latest_linux.zip
    rm -f /tmp/pg_featureserv_latest_linux.zip

    pushd config

      sed -i.save "s|# DbConnection = \"postgresql://username:password@host/dbname\"|DbConnection = \"postgresql://${APPUSER}:${APPUSER_PG_PASS}@localhost/${APPDB}\"|" pg_featureserv.toml.example
      sed -i.save "s|^AssetsPath =.*|AssetsPath = \"${FEATSERV_HOME}/assets\"|g" pg_featureserv.toml.example
      sed -i.save 's/^HttpHost = .*/HttpHost = "0.0.0.0"/' pg_featureserv.toml.example
      sed -i.save 's/^HttpPort = .*/HttpPort = 9000/' pg_featureserv.toml.example
      sed -i.save 's/^HttpsPort =/#HttpsPort =/' pg_featureserv.toml.example
      
      mv pg_featureserv.toml.example pg_featureserv.toml
    popd

  popd

  chown -R ${APPUSER}:${APPUSER} ${FEATSERV_HOME}

  cat >/etc/systemd/system/pg_featureserv.service <<CMD_EOF
[Unit]
Description=PG FeatureServ
After=multi-user.target

[Service]
User=${APPUSER}
WorkingDirectory=${FEATSERV_HOME}
Type=simple
Restart=always
ExecStart=${FEATSERV_HOME}/pg_featureserv --config ${FEATSERV_HOME}/config/pg_featureserv.toml

[Install]
WantedBy=multi-user.target
CMD_EOF


  systemctl daemon-reload
  systemctl enable pg_featureserv
  systemctl start pg_featureserv

}

function install_pg_routing(){
  sudo -u postgres psql -d ${APPDB} <<CMD_EOF
CREATE OR REPLACE
FUNCTION public.boston_nearest_id(geom geometry)
RETURNS bigint
AS \$\$
    SELECT node.id
    FROM ways_vertices_pgr node
    JOIN ways edg
      ON (node.id = edg.source OR    -- Only return node that is
          node.id = edg.target)      --   an edge source or target.
    WHERE edg.source != edg.target   -- Drop circular edges.
    ORDER BY node.the_geom <-> \$1    -- Find nearest node.
    LIMIT 1;
\$\$ LANGUAGE 'sql'
STABLE
STRICT
PARALLEL SAFE;

CREATE OR REPLACE
FUNCTION ${APPDB}.boston_find_route(
    from_lon FLOAT8 DEFAULT -71.07246980438231,
    from_lat FLOAT8 DEFAULT 42.3439930733156,
    to_lon FLOAT8 DEFAULT -71.06028184661864,
    to_lat FLOAT8 DEFAULT 42.354491297186655)
RETURNS
  TABLE(path_seq integer,
        edge bigint,
        cost double precision,
        agg_cost double precision,
        geom geometry)
AS \$\$
    BEGIN
    RETURN QUERY
    WITH clicks AS (
    SELECT
        ST_SetSRID(ST_Point(from_lon, from_lat), 4326) AS start,
        ST_SetSRID(ST_Point(to_lon, to_lat), 4326) AS stop
    )
    SELECT dijk.path_seq, dijk.edge, dijk.cost, dijk.agg_cost, ways.the_geom AS geom
    FROM ways
    CROSS JOIN clicks
    JOIN pgr_dijkstra(
        'SELECT gid as id, source, target, length_m as cost, length_m as reverse_cost FROM ways',
        -- source
        boston_nearest_id(clicks.start),
        -- target
        boston_nearest_id(clicks.stop)
        ) AS dijk
        ON ways.gid = dijk.edge;
    END;
\$\$ LANGUAGE 'plpgsql'
STABLE
STRICT
PARALLEL SAFE;
CMD_EOF

  #get the routing web UI
  sed -i.save "
s/var serverName =.*/var serverName = '${HNAME}'/
s/:7800\/public.ways/:7800\/tile\/public.ways/
" /var/www/html/openlayers-pgrouting.html

  systemctl restart pg_tileserv pg_featureserv
}

function info_for_user()

{

#End message for user

echo -e "Installation is now completed."
echo -e "Access pg-tileserv at ${HNAME}:7800"
echo -e "Access pg-featureserv at ${HNAME}:9000"
echo -e "Access pg-routing at ${HNAME}/openlayers-pgrouting.html"
echo -e "postgres and crunchy pg passwords are saved in /root/auth.txt file"
	
	if [ ${BUILD_SSL} == 'yes' ]; then
		if [ ! -f /etc/letsencrypt/live/${HNAME}/privkey.pem ]; then
			echo 'SSL Provisioning failed.  Please see geohelm.docs.acugis.com for troubleshooting tips.'
		else
			echo 'SSL Provisioning Success.'
		fi
	fi
}

function setup_user(){
  useradd -m ${APPUSER}

  echo "${APPDB}:${APPUSER}:${APPUSER_PG_PASS}" >/home/${APPUSER}/.pgpass
  chown ${APPUSER}:${APPUSER} /home/${APPUSER}/.pgpass
  chmod 0600 /home/${APPUSER}/.pgpass
}

function install_bootstrap_app(){
	wget --quiet -P/tmp https://github.com/AcuGIS/GeoHelm/archive/refs/heads/master.zip
	unzip /tmp/master.zip -d/tmp

	cp -r /tmp/GeoHelm-master/app/* /var/www/html/
	mv /tmp/GeoHelm-master/app/data /opt/

	rm -rf /tmp/master.zip
	
	#update app
	find /var/www/html/ -type f -not -path "/var/www/html/latest/*" -name "*.html" -exec sed -i.save "s/MYLOCALHOST/${HNAME}/g" {} \;
	sed -i.save "s/MYPGISPASSWORD/${APPUSER_PG_PASS}/" /var/www/html/get-json.php
}

function install_openlayers(){
  OL_VER=$(wget -q -L -O- https://github.com/openlayers/openlayers/releases/latest | grep '<title>Release' | sed 's/.*v\([0-9\.]\+\).*/\1/')
  wget --quiet -P/tmp "https://github.com/openlayers/openlayers/releases/download/v${OL_VER}/v${OL_VER}-package.zip"

	mkdir /var/www/html/OpenLayers
	pushd /var/www/html/OpenLayers
	  unzip -u /tmp/v${OL_VER}-package.zip
	popd
	rm -f /tmp/v${OL_VER}-package.zip

  chown -R apache:apache /var/www/html/OpenLayers
}

function install_leafletjs(){
  LL_VER=$(wget -q -O- 'https://leafletjs.com/download.html' | sed -n 's/.*\/leaflet\/v\([0-9\.]\+\)\/leaflet\.zip.*/\1/p' | sort -rn | head -1)

  wget --quiet -P/tmp "https://leafletjs-cdn.s3.amazonaws.com/content/leaflet/v${LL_VER}/leaflet.zip"

  unzip /tmp/leaflet.zip -d /var/www/html/leafletjs
  rm -f /tmp/leaflet.zip
  chown -R apache:apache /var/www/html/leafletjs
  
  dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm 
  dnf module enable php:remi-8.1 -y
  dnf install php-{common,gmp,fpm,curl,intl,pdo,mbstring,gd,xml,cli,zip,pgsql} -y
}

function install_nlohmann(){
	wget --quiet -P/tmp https://github.com/nlohmann/json/releases/download/v3.11.2/include.zip
	
	pushd /usr/
		unzip /tmp/include.zip
	popd
	
	rm -rf /tmp/include.zip
}

function install_osm2pgsql_source(){
		
	dnf install -y cmake bzip2-devel git zlib-devel expat-devel lua-devel boost-devel
	
	wget -P/tmp https://github.com/openstreetmap/osm2pgsql/archive/master.zip
	unzip -ou /tmp/master.zip
	rm -f /tmp/master.zip

	pushd osm2pgsql-master
		mkdir build
		pushd build
			cmake -DCMAKE_INSTALL_PREFIX:PATH=/usr ..
			make -j $(cat /proc/cpuinfo  | grep -c processor)
			make install
		popd
	popd
	rm -rf osm2pgsql-master
}

function install_osm2pgrouting_source(){
	
	dnf install -y libpqxx-devel
	
	wget -P/tmp https://github.com/pgRouting/osm2pgrouting/archive/master.zip
	unzip -ou /tmp/master.zip
	rm -rf /tmp/master.zip

	pushd osm2pgrouting-main
		mkdir build
		pushd build
			cmake -DCMAKE_INSTALL_PREFIX:PATH=/usr -DPOSTGRESQL_LIBRARIES="$(pg_config --libdir)/libpq.so" ..
			make -j $(cat /proc/cpuinfo  | grep -c processor)
			make install
		popd
	popd
	rm -rf osm2pgrouting-main
}

function install_postgis_pkgs(){
  dnf install -y postgis33_${PG_VER} postgis33_${PG_VER}-client pgrouting_${PG_VER}
	
	#osm2pg{sql,routing} are not available in Rocky Linux
	dnf install -y cmake make gcc-c++ boost-devel expat-devel zlib-devel \
  	bzip2-devel proj-devel lua-devel libpq-devel libpqxx-devel
}


function install_webmin(){
	wget -P/tmp 'https://download.webmin.com/developers-key.asc'
	rpm --import /tmp/developers-key.asc || true
	cp -f /tmp/developers-key.asc /etc/pki/rpm-gpg/RPM-GPG-KEY-webmin-developers

  cat >/etc/yum.repos.d/webmin.repo <<EOF
[Webmin]
name=Webmin Distribution Neutral
baseurl=https://download.webmin.com/download/newkey/yum
enabled=1
gpgcheck=1
gpgkey=file:///etc/pki/rpm-gpg/RPM-GPG-KEY-webmin-developers
EOF

  dnf --nogpgcheck install -y webmin tar rsync
	
	mkdir -p /etc/webmin/authentic-theme
	cp -r /var/www/html/portal/*  /etc/webmin/authentic-theme
}

function install_geoserver_module(){
	pushd /tmp/GeoHelm-master/
    
		tar -czf geoserver.wbm.gz geoserver
		rm -rf geoserver

    /usr/libexec/webmin/install-module.pl geoserver.wbm.gz
		rm -rf geoserver.wbm.gz
  popd
}


function install_postgis_module(){

	pushd /tmp/GeoHelm-master/
    rm -f postgis/setup.cgi
    tar -czf postgis.wbm.gz postgis
    rm -rf postgis

    /usr/libexec/webmin/install-module.pl postgis.wbm.gz
		rm -rf postgis.wbm.gz
  popd
  
}

function install_certbot_module(){

	dnf install -y python3-certbot-apache certbot mod_ssl
	
	systemctl restart httpd

  pushd /opt/
    wget --quiet https://github.com/cited/Certbot-Webmin-Module/archive/master.zip
    unzip master.zip
    mv Certbot-Webmin-Module-master certbot
    tar -czf /opt/certbot.wbm.gz certbot
    rm -rf certbot master.zip

    /usr/libexec/webmin/install-module.pl certbot.wbm.gz
		rm -rf certbot.wbm.gz
  popd
}


function install_tomcat(){

	#dnf install -y haveged

	if [ ! -d /home/tomcat ]; then
		useradd -m tomcat
	fi
	cd /home/tomcat

	if [ ! -d apache-tomcat-${TOMCAT_VER} ]; then
		if [ ! -f /tmp/apache-tomcat-${TOMCAT_VER}.tar.gz ]; then
			wget -q -P/tmp "https://archive.apache.org/dist/tomcat/tomcat-${TOMCAT_MAJOR}/v${TOMCAT_VER}/bin/apache-tomcat-${TOMCAT_VER}.tar.gz"
		fi
		tar xzf /tmp/apache-tomcat-${TOMCAT_VER}.tar.gz
		chown -R tomcat:tomcat apache-tomcat-${TOMCAT_VER}
		rm -rf /tmp/apache-tomcat-${TOMCAT_VER}.tar.gz
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
		
	if [ $(sestatus | grep -cm1 enabled) -eq 1 ]; then
		find ${CATALINA_HOME}/bin/ -type f -name "*.sh" -exec  semanage fcontext --add --type initrc_exec_t {} \;
		restorecon -rv ${CATALINA_HOME}/bin
	fi

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
	
	cat >>/etc/httpd/conf.d/geoserver.conf <<EOF
ProxyPass        /geoserver   http://localhost:8080/geoserver
ProxyPassReverse /geoserver   http://localhost:8080/geoserver
EOF
	
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
	dnf install -y java-11-openjdk-headless
}

function menu(){	
	# disable error flag
	set +e
	
	SUITE_FLAVOR=$(whiptail --title "GeoHelm Installer" --menu \
									"Select the GeoHelm version you want to install:" 20 78 4 \
									"GeoHelm Full Installation" " " 3>&1 1>&2 2>&3)
	
	exitstatus=$?
	if [ $exitstatus != 0 ]; then
		echo "GeoHelm installation cancelled."
		exit 1
	fi
	
	# set options based on flavor we have
	case ${SUITE_FLAVOR} in
		"GeoHelm Full Installation")
			;;
	esac

	whiptail --title "Hostname is $(hostname -f)" --yesno \
		--yes-button "Continue" --no-button "Quit" \
		"Be sure to set the hostname if you wish to use SSL" 8 78
	
	exitstatus=$?
	if [ $exitstatus != 0 ]; then
	    exit 0
	fi

	whiptail --title "GeoHelm can provision SSL for ${HNAME}" --yesno \
		"Provision SSL for  ${HNAME}?" 8 78
	
	exitstatus=$?
	if [ $exitstatus == 0 ]; then
			BUILD_SSL='yes'
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
	
	dnf module disable -y postgresql
	dnf config-manager --enable crb		# PowerTools
	
	dnf install -y wget unzip tar httpd bzip2 epel-release policycoreutils-python-utils
	
	# Get Tomcat latest version and set CATALINA_HOME
	TOMCAT_VER=$(wget -q -O- --no-check-certificate https://archive.apache.org/dist/tomcat/tomcat-${TOMCAT_MAJOR}/ | sed -n "s|.*<a href=\"v\(${TOMCAT_MAJOR}\.[0-9.]\+\)/\">v.*|\1|p" | sort -V | tail -n 1)
	if [ -z "${TOMCAT_VER}" ]; then
		echo "Error: Failed to get tomcat version"; exit 1;
	fi
	CATALINA_HOME="/home/tomcat/apache-tomcat-${TOMCAT_VER}"

	GEO_VER=$(wget http://geoserver.org/release/stable/ -O- 2>/dev/null | sed -n 's/^[ \t]\+<h1>GeoServer \(.*\)<\/h1>.*/\1/p')
	
	cat >/etc/httpd/conf.d/default.conf <<CAT_EOF
<VirtualHost *:80>
	ServerAdmin webmaster@localhost
	DocumentRoot /var/www/html
	ServerName ${HNAME}
</VirtualHost>
CAT_EOF
}

function setup_firewalld(){
  #apache and django app
  firewall-cmd --permanent --zone=public --add-port=80/tcp
	firewall-cmd --permanent --zone=public --add-port=443/tcp
  
	firewall-cmd --permanent --zone=public --add-port=8080/tcp
	firewall-cmd --permanent --zone=public --add-port=10000/tcp
	
  #crunchy services
  firewall-cmd --permanent --zone=public --add-port=7800/tcp
  firewall-cmd --permanent --zone=public --add-port=9000/tcp

	  systemctl reload firewalld
  sleep 10;
}

function setup_selinux(){
	
  #allow apache port for django app
  semanage port -a -t http_port_t -p tcp 7800
  semanage port -m -t http_port_t -p tcp 9000

  setsebool -P httpd_can_network_connect 1
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
  done | whiptail --gauge "Please wait while install completes..." 6 50 0
}

function provision_ssl(){
	/bin/bash /tmp/build-ssl.sh || true
}

################################################################################

menu;

declare -x STEPS=(
  'Checking Requirements...'
  'Installing Demo Data....'
	'Installing Libraries....'
	'Installing LeafletJS Apps...'
	'Setting Up Users...'
	'Installing PostgreSQL Repository....'
	'Installing PostGIS Packages....'
	'Installing NLOHMAN/JSON lib...'
	'Compiling osm2pgsql'
	'Compiling osm2pgrouting'
	'Creating Crunchy Database....'
	'Loading Crunchy Data...'
	'Installing pg_tileserv'
	'Installing pg_featurserv'
	'Installing pg_routing'
	'Installing Java....'
)
declare -x CMDS=(
	'install_deps'
	'install_bootstrap_app'
	'install_openlayers'
	'install_leafletjs'
	'setup_user'
	'install_postgresql'
	'install_postgis_pkgs'
	'install_nlohmann'
	'install_osm2pgsql_source'
	'install_osm2pgrouting_source'
	'crunchy_setup_pg'
	'load_pg_data'
	'install_pg_tileserv'
	'install_pg_featureserv'
	'install_pg_routing'
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

# setup SELinux only if enabled
if [ $(sestatus | grep -cm1 enabled) -eq 1 ]; then
	STEPS+=("SELinux setup")
	CMDS+=("setup_selinux")
fi

# setup firewalld only if installed and enabled
if [ -f /usr/bin/firewall-cmd ] && [ $(firewall-cmd --state | grep -cm1 running) -eq 1 ]; then
	STEPS+=("Firewalld setup")
	CMDS+=("setup_firewalld")
fi

if [ ${BUILD_SSL} == 'yes' ]; then
	STEPS+=("Provisioning SSL")
	CMDS+=('provision_ssl')
fi

# -------------------- #

whiptail_gauge;
info_for_user

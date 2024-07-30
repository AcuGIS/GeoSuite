#!/bin/bash -e
#GeoSuite Script for enabling CORS
#Usage:
#wget https://raw.githubusercontent.com/AcuGIS/GeoSuite/master/scripts/csrf-whitelist.sh
#chmod +x csrf-whitelist.sh
#./csrf-whitelist.sh

# Read the input to a variable
read -p "Domain to whitelist: " DOMAIN

function update_domain(){
	if [ -d '/usr/share/webmin' ]; then
	        sed -i 's/yourdomain.com/$DOMAIN/g' /usr/share/webmin/geosuite/scripts/csrf-whitelist.txt
	elif [ -d '/usr/libexec/webmin' ]; then
		sed -i 's/yourdomain.com/$DOMAIN/g' /usr/libexec/webmin/geosuite/scripts/csrf-whitelist.txt
	fi
}

function enable_csrf(){
	if [ -d '/usr/share/webmin' ]; then
	        sed -i.save $'/<\/web-app>/{e cat /usr/share/webmin/geosuite/scripts/csrf-whitelist.txt\n}' $CATALINA_HOME/webapps/geoserver/WEB-INF/web.xml
	elif [ -d '/usr/libexec/webmin' ]; then
		sed -i.save $'/<\/web-app>/{e cat /usr/libexec/webmin/geosuite/scripts/csrf-whitelist.txt\n}' $CATALINA_HOME/webapps/geoserver/WEB-INF/web.xml
	fi
}

update_domain;
enable_csrf;

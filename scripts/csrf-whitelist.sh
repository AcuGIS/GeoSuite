#!/bin/bash -e
#GeoHelm Script for enabling CORS
#Usage:
#wget https://raw.githubusercontent.com/AcuGIS/GeoHelm/master/scripts/csrf-whitelist.sh
#chmod +x csrf-whitelist.sh
#./csrf-whitelist.sh

function get_repo(){
	if [ -f /etc/centos-release ]; then
		REPO='rpm'
 
	elif [ -f /etc/debian_version ]; then
		REPO='apt'
fi
}


function enable_csrf(){
	if [ "${REPO}" == 'apt' ]; then
		sed -i.save $'/<\/web-app>/{e cat /usr/share/webmin/geohelm/scripts/csrf-whitelist.txt\n}' $CATALINA_HOME/webapps/geoserver/WEB-INF/web.xml
	elif [ "${REPO}" == 'rpm' ]; then
		sed -i.save $'/<\/web-app>/{e cat /usr/libexec/webmin/geohelm/scripts/csrf-whitelist.txt\n}' $CATALINA_HOME/webapps/geoserver/WEB-INF/web.xml
	fi
}

enable_csrf;

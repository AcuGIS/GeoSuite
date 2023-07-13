#!/bin/bash -e
#GeoServer Script for enabling CORS
#Usage:
#wget https://raw.githubusercontent.com/AcuGIS/GeoServer/master/scripts/enable-cors.sh
#chmod +x enable-cors.sh
#./enable-cors.sh

function get_repo(){
	if [ -f /etc/centos-release ]; then
		REPO='rpm'
 
	elif [ -f /etc/debian_version ]; then
		REPO='apt'
fi
}


function enable_cors(){
	if [ "${REPO}" == 'apt' ]; then
		sed -i.save $'/<\/web-app>/{e cat /usr/share/webmin/geoserver/scripts/cors.txt\n}' $CATALINA_HOME/conf/web.xml
	elif [ "${REPO}" == 'rpm' ]; then
		sed -i.save $'/<\/web-app>/{e cat /usr/libexec/webmin/geoserver/scripts/cors.txt\n}' $CATALINA_HOME/conf/web.xml
	fi
}

enable_cors;

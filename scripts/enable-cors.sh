#!/bin/bash -e
#GeoHelm Script for enabling CORS
#Usage:
#wget https://raw.githubusercontent.com/AcuGIS/GeoHelm/master/scripts/enable-cors.sh
#chmod +x enable-cors.sh
#./enable-cors.sh

function enable_cors(){


sed -i.save $'/<\/web-app>/{e cat /usr/share/webmin/geohelm/scripts/cors.txt\n}' $CATALINA_HOME/conf/web.xml

}

enable_cors;

#!/bin/bash -e
#GeoHelm Pre-Install Script for Debian and Ubuntu
#For use on clean Debian or Ubuntu box only
#Usage: 
#wget https://raw.githubusercontent.com/downloads/AcuGIS/geohelm-quick-start/geohelm-ubuntu.sh
#chmod +x geohelm-ubuntu.sh
#./geohelm-ubuntu.sh

function install_webmin(){
	#http://doxfer.webmin.com/Webmin/Installation
	echo "deb http://download.webmin.com/download/repository sarge contrib" > /etc/apt/sources.list.d/webmin.list
	wget -qO - http://www.webmin.com/jcameron-key.asc | apt-key add -
	apt-get -y update
	apt-get -y install webmin
}
function download_geohelm_module(){
	pushd /tmp/
 
	wget https://github.com/AcuGIS/GeoHelm/archive/master.zip
	unzip master.zip
	mv GeoHelm-master geohelm
	tar -czf /opt/geohelm.wbm.gz geohelm
	rm -rf geohelm master.zip
       
 
	popd
}
function warn_apache(){
 echo -e "Webmin is now installed and GeoHelm module is at /opt/geohelm.wbm.gz"
 echo -e "Important: Install Apache via Webmin BEFORE installing GeoHelm!!!" 
}

apt-get -y update
apt-get -y install wget unzip

install_webmin;
download_geohelm_module;
warn_apache;

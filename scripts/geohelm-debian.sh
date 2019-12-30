#!/bin/bash -e
#GeoHelm Pre-Install Script for Debian and Ubuntu
#For use on clean Debian or Ubuntu box only
#Usage:
#wget https://raw.githubusercontent.com/AcuGIS/GeoHelm/master/scripts/geohelm-debian.sh
#chmod +x geohelm-debian.sh
#./geohelm-debian.sh

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

function download_certbot_module(){
	pushd /tmp/

	wget https://github.com/AcuGIS/Certbot-Webmin-Module/archive/master.zip
	unzip master.zip
	mv Certbot-Webmin-Module-master certbot
	tar -czf /opt/certbot.wbm.gz certbot
	rm -rf certbot master.zip

	popd
	
}

function install_apache(){
		
	apt-get install -y apache2        
	
}

function install_geohelm_module(){
	pushd /opt/

	/usr/share/webmin/install-module.pl geohelm.wbm.gz
	
	popd
	
}

function install_certbot_module(){
	pushd /opt/

	/usr/share/webmin/install-module.pl certbot.wbm.gz
	
	popd
	echo -e "GeoHelm is now installed. Go to Servers > GeoHelm to complete installation"
	
}

apt-get -y update
apt-get -y install wget unzip

install_webmin;
download_geohelm_module;
install_geohelm_module;
install_apache;
download_certbot_module;
install_certbot_module;

#!/bin/bash -e
#GeoServer Pre-Install Script for Fedora
#For use on clean Fedora 24 or 25 box only!!

#Usage: wget https://raw.githubusercontent.com/downloads/AcuGIS/geoserver-quick-start/geoserver-fedora.sh
#chmod +x geoserver-fedora.sh
#./geoserver-fedora.sh

function install_webmin(){
	#http://doxfer.webmin.com/Webmin/Installation
	cat >/etc/yum.repos.d/webmin.repo <<EOF
[Webmin]
name=Webmin Distribution Neutral
baseurl=http://download.webmin.com/download/yum
enabled=1
gpgcheck=1
gpgkey=http://www.webmin.com/jcameron-key.asc
EOF
	dnf -y install webmin
}

function download_geoserver_module(){
	pushd /tmp/

	wget https://github.com/AcuGIS/GeoServer/archive/master.zip
	unzip master.zip
	mv GeoServer-master geoserver
	tar -czf /opt/geoserver.wbm.gz geoserver
	rm -rf geoserver master.zip

	popd
  echo -e "Webmin is now installed and GeoServer module is at /opt/geoserver.wbm.gz"
}

dnf -y install wget unzip bzip2

install_webmin;
download_geoserver_module;

#!/bin/bash -e
#GeoServer Pre-Install Script for Scientific Linux
#For use on clean Scientific Linux box only!!
#Usage: wget https://raw.githubusercontent.com/AcuGIS/GeoServer/master/scripts/geoserver-scientific-linux.sh
#chmod +x geoserver-scientific-linux.sh
#./geoserver-geoserver-scientific-linux.sh

function install_webmin(){
	cat >/etc/yum.repos.d/webmin.repo <<EOF
[Webmin]
name=Webmin Distribution Neutral
baseurl=http://download.webmin.com/download/yum
enabled=1
gpgcheck=1
gpgkey=http://www.webmin.com/jcameron-key.asc
EOF
	yum -y install webmin
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

yum -y update;
yum -y install wget unzip epel-release


install_webmin;
download_geoserver_module;

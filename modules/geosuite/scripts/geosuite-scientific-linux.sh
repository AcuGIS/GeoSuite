#!/bin/bash -e
#GeoSuite Pre-Install Script for Scientific Linux
#For use on clean Scientific Linux box only!!
#Usage: wget https://raw.githubusercontent.com/AcuGIS/GeoSuite/master/scripts/geosuite-scientific-linux.sh
#chmod +x geosuite-scientific-linux.sh
#./geosuite-geosuite-scientific-linux.sh

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

function download_geosuite_module(){
	pushd /tmp/

	wget https://github.com/AcuGIS/GeoSuite/archive/master.zip
	unzip master.zip
	mv GeoSuite-master geosuite
	tar -czf /opt/geosuite.wbm.gz geosuite
	rm -rf geosuite master.zip

	popd
	echo -e "Webmin is now installed and GeoSuite module is at /opt/geosuite.wbm.gz"
}

yum -y update;
yum -y install wget unzip epel-release


install_webmin;
download_geosuite_module;

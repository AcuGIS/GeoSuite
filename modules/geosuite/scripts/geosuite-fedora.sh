#!/bin/bash -e
#GeoSuite Pre-Install Script for Fedora
#For use on clean Fedora 24 or 25 box only!!

#Usage: wget https://raw.githubusercontent.com/downloads/AcuGIS/geosuite-quick-start/geosuite-fedora.sh
#chmod +x geosuite-fedora.sh
#./geosuite-fedora.sh

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

dnf -y install wget unzip bzip2

install_webmin;
download_geosuite_module;

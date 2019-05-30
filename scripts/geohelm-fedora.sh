#!/bin/bash -e
#GeoHelm Pre-Install Script for Fedora
#For use on clean Fedora 24 or 25 box only!!

#Usage: wget https://raw.githubusercontent.com/downloads/AcuGIS/geohelm-quick-start/geohelm-fedora.sh
#chmod +x geohelm-fedora.sh
#./geohelm-fedora.sh

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

function download_geohelm_module(){
	pushd /tmp/

	wget https://github.com/AcuGIS/GeoHelm/archive/master.zip
	unzip master.zip
	mv GeoHelm-master geohelm
	tar -czf /opt/geohelm.wbm.gz geohelm
	rm -rf geohelm master.zip

	popd
  echo -e "Webmin is now installed and GeoHelm module is at /opt/geohelm.wbm.gz"
}

dnf -y install wget unzip bzip2

install_webmin;
download_geohelm_module;

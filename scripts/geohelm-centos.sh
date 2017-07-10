#!/bin/bash -e
#GeoHelm Pre-Install Script for CentOS
#For use on clean CentOS 7 box only!!

#Usage: wget https://raw.githubusercontent.com/AcuGIS/GeoHelm/master/scripts/geohelm-centos.sh
#chmod +x geohelm-centos.sh
#./geohelm-centos.sh

 
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

 
yum -y install wget unzip bzip2
 
install_webmin;
download_geohelm_module;
warn_apache;

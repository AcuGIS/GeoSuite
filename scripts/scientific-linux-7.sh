#!/bin/bash -e
 
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
 
yum -y update;
yum -y install wget unzip epel-release
 
 
install_webmin;
download_geohelm_module;

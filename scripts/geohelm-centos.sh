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
		
	yum -y install httpd        
	
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
	echo -e "GeoHelm is now installed.  Please go to Servers > GeoHelm to complete installation"
	
}
yum -y install wget unzip bzip2
install_webmin;
download_geohelm_module;
install_apache;
download_certbot_module;
install_certbot_module;

#!/bin/bash -e
#GeoSuite Pre-Install Script for Debian and Ubuntu
#For use on clean Debian or Ubuntu box only
#Usage:
#wget https://raw.githubusercontent.com/AcuGIS/GeoSuite/master/scripts/pre-install.sh
#chmod +x pre-install.sh
#./pre-install.sh

function get_repo(){
	if [ -f /etc/centos-release ]; then
		REPO='rpm'
 
	elif [ -f /etc/debian_version ]; then
		REPO='apt'
fi
}

function install_webmin(){

	if [ "${REPO}" == 'apt' ]; then

	echo "deb http://download.webmin.com/download/repository sarge contrib" > /etc/apt/sources.list.d/webmin.list
	wget -qO - http://www.webmin.com/jcameron-key.asc | apt-key add -
	apt-get -y update
	apt-get -y install webmin

	elif [ "${REPO}" == 'rpm' ]; then

cat >/etc/yum.repos.d/webmin.repo <<EOF
[Webmin]
name=Webmin Distribution Neutral
#baseurl=http://download.webmin.com/download/yum
mirrorlist=http://download.webmin.com/download/yum/mirrorlist
enabled=1
EOF
		wget http://www.webmin.com/jcameron-key.asc
		rpm --import jcameron-key.asc
		yum -y install webmin

	fi
}	

function download_geosuite_module(){
pushd /tmp/
	wget https://github.com/AcuGIS/GeoSuite/archive/master.zip
	unzip master.zip
	mv GeoSuite-master geosuite
	tar -czf /opt/geosuite.wbm.gz geosuite
	rm -rf geosuite master.zip
popd
  
}

function download_certbot_module(){
pushd /tmp/
	wget https://github.com/cited/Certbot-Webmin-Module/archive/master.zip
	unzip master.zip
	mv Certbot-Webmin-Module-master certbot
	tar -czf /opt/certbot.wbm.gz certbot
	rm -rf certbot master.zip
popd
}

function install_apache(){
	if [ "${REPO}" == 'apt' ]; then
		apt-get -y install apache2
	elif [ "${REPO}" == 'rpm' ]; then
		yum -y install httpd
	fi
}

function install_geosuite_module(){
pushd /opt/
        if [ "${REPO}" == 'apt' ]; then
       	/usr/share/webmin/install-module.pl geosuite.wbm.gz
        elif [ "${REPO}" == 'rpm' ]; then
        /usr/libexec/webmin/install-module.pl geosuite.wbm.gz
        fi
popd
        echo -e "GeoSuite is now installed. Go to Servers > GeoSuite to complete installation"
	
}

function install_certbot_module(){
pushd /opt/
	if [ "${REPO}" == 'apt' ]; then
	/usr/share/webmin/install-module.pl certbot.wbm.gz
        elif [ "${REPO}" == 'rpm' ]; then
        /usr/libexec/webmin/install-module.pl certbot.wbm.gz
        fi
popd
        echo -e "Certbot is now installed. Go to Servers > Certbot to complete installation"
	
}

function get_deps(){
if [ "${REPO}" == 'apt' ]; then
		apt-get -y install wget unzip
	elif [ "${REPO}" == 'rpm' ]; then
		yum -y install wget unzip bzip2
    fi
}

function install_apache(){
	if [ "${REPO}" == 'apt' ]; then
		apt-get -y install apache2
	elif [ "${REPO}" == 'rpm' ]; then
		yum -y install httpd
	fi
}

get_repo;
get_deps;
install_webmin;
install_apache;
download_geosuite_module;
download_certbot_module;
install_certbot_module;
install_geosuite_module;

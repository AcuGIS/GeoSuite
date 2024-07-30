# !/bin/bash -e
function install_css(){

        mkdir -p /etc/webmin/authentic-theme
	cp -r /usr/share/webmin/geosuite/app/portal/*  /etc/webmin/authentic-theme
	
}

install_css;

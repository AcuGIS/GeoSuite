#!/bin/bash -e

HNAME=$(hostname | sed -n 1p | cut -f1 -d' ' | tr -d '\n')

function request_cert() {
	certbot --apache --agree-tos --email hostmaster@${HNAME} --no-eff-email -d ${HNAME}
	sleep 12;
}


function webmin_ssl() {
	cat /etc/letsencrypt/live/${HNAME}/cert.pem > /etc/webmin/miniserv.pem
	cat /etc/letsencrypt/live/${HNAME}/privkey.pem >> /etc/webmin/miniserv.pem
	echo "extracas=/etc/letsencrypt/live/${HNAME}/fullchain.pem" >> /etc/webmin/miniserv.conf
	
	systemctl restart webmin
}

function restart_apache() {
	systemctl restart apache2	
}


request_cert;
webmin_ssl;
restart_apache;

#!/bin/bash -e

HNAME=$(hostname | sed -n 1p | cut -f1 -d' ' | tr -d '\n')

function request_cert() {
	certbot --apache --agree-tos --email hostmaster@${HNAME} --no-eff-email -d ${HNAME}
	sleep 12;
}


function get_repo(){
	if [ -f /etc/rocky-release ]; then
		REPO='rpm'
		
	elif [ -f /etc/debian_version ]; then
		REPO='apt'
	fi
}

function webmin_ssl() {
	cat /etc/letsencrypt/live/${HNAME}/cert.pem > /etc/webmin/miniserv.pem
	cat /etc/letsencrypt/live/${HNAME}/privkey.pem >> /etc/webmin/miniserv.pem
	echo "extracas=/etc/letsencrypt/live/${HNAME}/fullchain.pem" >> /etc/webmin/miniserv.conf
	
	systemctl restart webmin
}

function restart_apache() {
	if [ "${REPO}" == 'apt' ]; then
		systemctl restart apache2
	elif [ "${REPO}" == 'rpm' ]; then
		systemctl restart httpd
	fi
}

function get_certs() {
	cp /etc/letsencrypt/live/${HNAME}/fullchain.pem /opt/pg_tileserv/fullchain.pem
	cp /etc/letsencrypt/live/${HNAME}/privkey.pem /opt/pg_tileserv/privkey.pem
	
	cp /etc/letsencrypt/live/${HNAME}/fullchain.pem /opt/pg_featureserv/fullchain.pem
	cp /etc/letsencrypt/live/${HNAME}/privkey.pem /opt/pg_featureserv/privkey.pem
}

function own_certs() {
	
	chown pgis:pgis /opt/pg_tileserv/fullchain.pem
	chown pgis:pgis /opt/pg_tileserv/privkey.pem
	chown pgis:pgis /opt/pg_featureserv/fullchain.pem
	chown pgis:pgis /opt/pg_featureserv/privkey.pem
	
}

function update_confs() {
	
	sed -i.save "s/HttpPort/#/g" /opt/pg_featureserv/config/pg_featureserv.toml
	sed -i.save "s|\# HttpsPort|HttpsPort|g" /opt/pg_featureserv/config/pg_featureserv.toml
	
	sed -i.save "s/HttpPort/#/g" /opt/pg_tileserv/config/pg_tileserv.toml
	sed -i.save "s|\# HttpsPort|HttpsPort|g" /opt/pg_tileserv/config/pg_tileserv.toml
}

function update_certs() {
	
	sed -i.save '2 i TlsServerCertificateFile = "fullchain.pem"' /opt/pg_featureserv/config/pg_featureserv.toml
	sed -i.save '3 i TlsServerPrivateKeyFile = "privkey.pem"' /opt/pg_featureserv/config/pg_featureserv.toml
	
	cat <<EOT >> /opt/pg_tileserv/config/pg_tileserv.toml
	TlsServerCertificateFile = "fullchain.pem"
	TlsServerPrivateKeyFile = "privkey.pem"
EOT
	
}

function restart_servs() {
	
	systemctl restart pg_tileserv
	systemctl restart pg_featureserv
}

request_cert;
get_certs;
own_certs;
update_confs;
update_certs;
restart_servs;
webmin_ssl;
restart_apache;


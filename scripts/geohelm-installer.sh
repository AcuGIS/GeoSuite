#!/bin/bash -e

DISTRO=$(grep -m 1 '^ID=' /etc/os-release | cut -f2 -d= | tr -d '"')
DISTRO_VER=$(grep '^VERSION_ID' /etc/os-release | tr -d '"' | cut -f2 -d= | cut -f1 -d.)

INSTALL_SCRIPT="geohelm-${DISTRO}-${DISTRO_VER}.sh"
URL_BASE="https://raw.githubusercontent.com/AcuGIS/GeoHelm/master/scripts"

if [ -f /usr/bin/wget ]; then
	wget -q "${URL_BASE}/${INSTALL_SCRIPT}"
	wget -q -P/tmp "${URL_BASE}/build-ssl.sh"
elif [ -f /usr/bin/curl ]; then
	curl --silent ${URL_BASE}/${INSTALL_SCRIPT} > ${INSTALL_SCRIPT}
	curl --silent ${URL_BASE}/build-ssl.sh > '/tmp/build-ssl.sh'
else
	echo "Error: No downloader (wget or curl)";
	exit 2
fi

if [ -f "${INSTALL_SCRIPT}" ]; then
	chmod +x ./${INSTALL_SCRIPT} /tmp/build-ssl.sh
	./${INSTALL_SCRIPT}
else
	echo 'Your distribution is not supported!'
	exit 1
fi	
	


** Quick Start


The Quick Start scripts install Webmin, Apache, create the GeoHelm module.

This does save a few minutes, but even standard installation only takes about 10 minutes.

    Connect to a fresh VM or server via SSH.
    Grab the Quick Start script for your operating system using WGET:

 

For CentOS 7:

    wget https://raw.githubusercontent.com/AcuGIS/GeoHelm/master/scripts/geohelm-centos.sh

 

For Debian 8 and 9 or Ubuntu 14 or 16

 

    wget https://raw.githubusercontent.com/AcuGIS/GeoHelm/master/scripts/geohelm-debian.sh

 

For Fedora 24 or 25:

 

    wget https://raw.githubusercontent.com/AcuGIS/GeoHelm/master/scripts/geohelm-fedora.sh

 

For Scientific Linux 7:

 

    wget https://raw.githubusercontent.com/AcuGIS/GeoHelm/master/scripts/geohelm-scientific-linux.sh

 

Make the script executable:

 

    chmod +x geohelm-centos.sh

 

Execute the script:

 

    ./geohelm-centos.sh

 

When the script completes it will have installed Webmin as well the GeoHelm module.

Go to Servers > GeoHelm to complete the installation.

 
Note: The following components are optional:

    GeoServer
    WebApp (this is simply a test page with 2 demo maps)
    OpenLayers
    Leafletjs
    GeoExplorer

If you don't want or need any of these, just click the "Dismiss" button next to them.

GeoServer is installed by clicking on the GeoServer button and the clicking "Install Now" button (just as you did with Apache Web Server above).

If you don't want or need GeoServer, simply do not install it.

If you accidentally installed GeoServer or GeoExplorer, you can easily remove them via GeoHelm > WARS 

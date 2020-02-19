
# Quick Start


The Quick Start scripts installs Webmin, Apache, and creates and installs the GeoHelm module.

1. Connect to a fresh VM via SSH.

2. Download the Quick Start for your Distro using WGET:

 

## CentOS 7:

    wget https://raw.githubusercontent.com/AcuGIS/GeoHelm/master/scripts/geohelm-centos.sh

 

## Debian 8 and 9 or Ubuntu 14 or 16

 

    wget https://raw.githubusercontent.com/AcuGIS/GeoHelm/master/scripts/geohelm-debian.sh

 

## Fedora 24 or 25:

 

    wget https://raw.githubusercontent.com/AcuGIS/GeoHelm/master/scripts/geohelm-fedora.sh

 

## Scientific Linux 7:

 

    wget https://raw.githubusercontent.com/AcuGIS/GeoHelm/master/scripts/geohelm-scientific-linux.sh

 

3. Make the script executable:

 

    chmod +x geohelm-centos.sh

 

4. Execute the script:

 

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

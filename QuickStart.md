
# Quick Start


The Quick Start scripts installs Webmin, Apache, and creates and installs the GeoHelm module.

1. Connect to a fresh VM via SSH.

2. Download the pre-install script using WGET:

 
    wget https://raw.githubusercontent.com/AcuGIS/GeoHelm/master/scripts/pre-install.sh

 
3. Make the script executable:

 

    chmod +x pre-install.sh

 

4. Execute the script:

 

    ./pre-install.sh

 

When the script completes it will have installed Webmin as well the GeoHelm module and our Certbot module (for SSL).

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

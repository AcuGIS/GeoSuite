# GeoHelm: Open Source GIS Suite

[![Documentation Status](https://readthedocs.org/projects/geohelm/badge/?version=latest)](https://geohelm.docs.acugis.com/en/latest/?badge=latest)


![GeoHelm Logo](docs/_static/GeoHelm-main.png)

GeoHelm installs, configures, and manages the <code>latest, stable</code> versions of:

<code>Apache Tomcat</code><br />
<code>OpenJDK</code><br />
<code>GeoServer</code><br />
<code>PostgreSQL</code><br />
<code>PostGIS</code><br />
<code>PgRouting</code><br />
<code>pg_tileserv</code><br />
<code>pg_featurserv</code><br />

It also provides browser-based management for all services (see screen shot below)

All software installed by GeoSuite is unmodified, so it does not limit, change, or impede normal SSH access or require specific usage.  <br />

The control panel and modules can be uninstalled and all components will continue to run.<br />



## Supported Operating Systems <br/>
		
<code>Ubuntu 22 LTS</code><br />
<code>Rocky Linux 9</code>

## System Requirements: <br />
Disk: <code>10 GB</code><br />
Memory: <code>2 GB (Minimum) </code><br /> 

## Installation

1. On a clean Ubuntu 22 or Rocky Linux 9 system, run below as root to launch the Installer::

      	wget https://raw.githubusercontent.com/AcuGIS/GeoSuite/master/scripts/geohelm-installer.sh && chmod +x geohelm-installer.sh && ./geohelm-installer.sh


2.  Select "Full Installation" and tab to OK

![GeoHelm Installer](docs/_static/Install-2.png)

3.  The Installer will prompt to check hostname and if you wish to enable SSL

![GeoHelm Installer](docs/_static/Install-3.png)

3.  The Installer will prompt if you wish to enable SSL

![GeoHelm Installer](docs/_static/Install-4.png)

Installation time on Ubuntu 22 is about 4 minutes.  Installation time on Rocky Linux can take up to 15 minutes due to required source build for osm2pgsql

![GeoHelm Installer](docs/_static/Install-5.png)

On completetion, below is displayed::

		Installation is now completed.
		Access pg-tileserv at rok.webgis1.com:7800
		Access pg-featureserv at rok.webgis1.com:9000
		postgres, Tomcat, and crunchy pg passwords are saved in /root/auth.txt file
		SSL Provisioning Success.

4. Click the Login link on the homepage to access the control panel.

## Documentation
GeoSuite Documentation is available at [GeoHelm Docs](https://www.acugis.com/geohelm/docs/)


[AcuGIS](https://www.acugis.com/), [GeoHelm](https://geohelm.org) &copy; 2023 [Cited, Inc. ](https://www.citedcorp.com)Cited, Inc. All Rights Reserved.

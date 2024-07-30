# GeoSuite: Open Source GIS Suite

[![Documentation Status](https://readthedocs.org/projects/geosuite/badge/?version=latest)](https://geosuite.docs.acugis.com/en/latest/?badge=latest)


![GeoHelm Logo](docs/_static/acugis-geosuite-github.png)




GeoSuite installs, configures, and manages the <code>latest, stable</code> versions of:

<code>Apache Tomcat</code><br />
<code>GeoServer</code><br />
<code>PostgreSQL</code><br />
<code>PostGIS</code><br />
<code>PgRouting</code><br />
<code>QGIS Server</code><br />
<code>QuartzMap</code><br />


It also provides browser-based management for all services (see screen shot below)

All software installed by GeoSuite is unmodified, so it does not limit, change, or impede normal SSH access or require specific usage.  <br />

The control panel and modules can be uninstalled and all components will continue to run.<br />



## Supported Operating Systems <br/>
		
<code>Ubuntu 22 LTS</code><br />
<code>Ubuntu 24 LTS</code>

## System Requirements: <br />
Disk: <code>15 GB</code><br />
Memory: <code>2 GB (Minimum) </code><br /> 

## Installation

1. On a clean Ubuntu 22 or 24 system, run below as root to launch the Installer::

      	wget https://raw.githubusercontent.com/AcuGIS/GeoSuite/master/scripts/geosuite-installer.sh && chmod +x geosuite-installer.sh && ./geosuite-installer.sh


2.  Select "GeoSuite Only" or "GeoSuite and QuartzMap" and tab to OK

![GeoSuite Installer](docs/_static/geosuite-install-screen-1.png)


3.  The Installer will prompt to check hostname and if you wish to enable SSL

![GeoSuite Installer](docs/_static/geosuite-install-screen-3.png)

3.  The Installer will prompt if you wish to enable SSL

![GeoSuite Installer](docs/_static/geosuite-install-screen-4.png)

Installation time on Ubuntu 24 is about 4 minutes without QuartzMap and up to 10 minutes with QuartzMap.  

On completetion, below is displayed::

		Installation is now completed.
		postgres, Tomcat, and crunchy pg passwords are saved in /root/auth.txt file
		SSL Provisioning Success.

4. Click the Login link on the homepage to access the control panel.

5. If you elected to enable QuartzMap, go to https://yourdomain.com/quartzmap/admin/setup.php and enter your information

![GeoSuite Installer](docs/_static/quartzmap-geosuite.png)



## Documentation
GeoSuite Documentation is available at [GeoSuite Docs](https://geosuite.docs.acugis.com)


[AcuGIS](https://www.acugis.com/), &copy; 2024 [Cited, Inc. ](https://www.citedcorp.com)Cited, Inc. All Rights Reserved.

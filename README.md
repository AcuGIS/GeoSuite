# GeoHelm: A new route to Open Source GIS

* Project page: https://www.acugis.com/geohelm
* Documentation: https://geohelm.docs.acugis.com 

![GeoHelm Logo](geohelm-top-banner.jpg)

GeoHelm is a Webmin module that installs, configures, and manages the <code>latest, stable</code> versions of:

<code>Apache Tomcat</code><br />
<code>Oracle Java or OpenJDK</code><br />
<code>PostgreSQL</code><br />
<code>PostGIS</code><br />
<code>PgRouting</code><br />
<code>GeoServer</code><br />

It also provides browser-based management for all services (see screen shot below)

All software installed by GeoHelm is unmodified, so it does not limit, change, or impede normal SSH access or require specific usage.  <br />

The entire module can even be uninstalled and all components will continue to run.<br />



## Supported Operating Systems <br/>
		
<code>CentOS 7</code><br />
<code>Ubuntu 18 LTS</code><br />

## System Requirements: <br />
Disk: <code>10 GB</code><br />
Memory: <code>1 GB (Minimum) </code><br /> 
User Access:<code>root access required</code><br />
Software Requirements: <code>Webmin</code><br />

# Install via Script:

      wget https://raw.githubusercontent.com/AcuGIS/GeoHelm/master/scripts/pre-install.sh
      chmod +x pre-install.sh
      ./pre-install.sh

Go to Webmin > Servers > GeoHelm to complete installation using the Wizard

# Install via Git:

Archive module

	$ git clone https://github.com/AcuGIS/GeoHelm
	$ mv GeoHelm-master geohelm
	$ tar -cvzf geohelm.wbm.gz geohelm/

Upload from Webmin->Webmin Configuration->Webmin Modules

Go to Webmin > Servers > GeoHelm to complete installation using the Wizard


## Documentation
GeoHelm Documentation is available at [GeoHelm Docs](https://www.acugis.com/geohelm/docs/)
		
## GeoHelm Installed:


![GeoHelm Installed](geohelm-header.png)

[AcuGIS](https://www.acugis.com/), [GeoHelm](https://geohelm.org) &copy; 2019 [Cited, Inc. ](https://www.citedcorp.com)Cited, Inc. All Rights Reserved.

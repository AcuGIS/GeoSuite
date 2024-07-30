# GeoSuite: A new route to Open Source GIS

* Project page: https://www.acugis.com/geosuite
* Documentation: https://geosuite.docs.acugis.com 

![GeoSuite Logo](geosuite-top-banner.jpg)

GeoSuite is a Webmin module that installs, configures, and manages the <code>latest, stable</code> versions of:

<code>Apache Tomcat</code><br />
<code>Oracle Java or OpenJDK</code><br />
<code>PostgreSQL</code><br />
<code>PostGIS</code><br />
<code>PgRouting</code><br />
<code>GeoServer</code><br />

It also provides browser-based management for all services (see screen shot below)

All software installed by GeoSuite is unmodified, so it does not limit, change, or impede normal SSH access or require specific usage.  <br />

The entire module can even be uninstalled and all components will continue to run.<br />



## Supported Operating Systems <br/>
		
<code>CentOS 7</code><br />
<code>Ubuntu 20 LTS</code><br />
<code>Ubuntu 22 LTS</code><br />

## System Requirements: <br />
Disk: <code>10 GB</code><br />
Memory: <code>1 GB (Minimum) </code><br /> 
User Access:<code>root access required</code><br />
Software Requirements: <code>Webmin</code><br />

# Install via Script:

      wget https://raw.githubusercontent.com/AcuGIS/GeoSuite/master/scripts/pre-install.sh
      chmod +x pre-install.sh
      ./pre-install.sh

Go to Webmin > Servers > GeoSuite to complete installation using the Wizard

# Quick Install (if Webmin already installed):

1. Log into Webmin
2. Go to Webmin Configuration > Webmin Modules
3. Select "From HTTP or FTP Url"
4. Enter https://github.com/AcuGIS/GeoSuite/blob/master/scripts/geosuite.wbm.gz?raw=true
5. Click the Install button.

# Install via Git:

Archive module

	$ git clone https://github.com/AcuGIS/GeoSuite
	$ mv GeoSuite-master geosuite
	$ tar -cvzf geosuite.wbm.gz geosuite/

Upload from Webmin->Webmin Configuration->Webmin Modules

Go to Webmin > Servers > GeoSuite to complete installation using the Wizard


## Documentation
GeoSuite Documentation is available at [GeoSuite Docs](https://www.acugis.com/geosuite/docs/)
		
## GeoSuite Installed:


![GeoSuite Installed](geosuite-header.png)

[AcuGIS](https://www.acugis.com/), [GeoSuite](https://geosuite.org) &copy; 2020 [Cited, Inc. ](https://www.citedcorp.com)Cited, Inc. All Rights Reserved.

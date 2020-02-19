GeoHelm
===========================

GeoHelm is a Webmin module that installs, configures, and manages the latest, stable versions of:

Apache Tomcat
Oracle Java or OpenJDK
PostgreSQL
PostGIS
PgRouting
GeoServer

Features
--------

- Install Tomcat
- Install JDK
- Stop, Start, and Restart Tomcat
- Edit Main Config Files
- Deploy WARS

Supported Operating Systems
--------------------------

CentOS 7.x 64
Debian 9 x64
Ubuntu 16 x64
Ubuntu 18 x64
Scientific Linux 7 x64 (requires manual installation for epel or use Quick Start Script)


System Requirements
-------------------

Disk: 5 GB
Memory: 1 GB (Minimum)
User Access:root access required
Software Requirements: Webmin


Installation
------------

A Quick Start is available at: GeoHelm Quick Start.
The Quick Start does save a few minutes, but even standard installation only takes about 10 minutes.

The preferred method is installing via GIT.

    $ git clone https://github.com/AcuGIS/GeoHelm

    $ mv GeoHelm-master geohelm

    $ tar -cvzf geohelm.wbm.gz geohelm/
    
    
Upload from Webmin->Webmin Configuration->Webmin Modules

Go to Servers->GeoHelm and follow the Set Up Wizard

Contribute
----------

- Issue Tracker: github.com/cited/Tomcat-Webmin-Module/issues
- Source Code: github.com/cited/Tomcat-Webmin-Module

Support
-------

If you are having issues, please let us know.
We have a mailing list located at: project@google-groups.com

License
-------

The project is licensed under the BSD license.

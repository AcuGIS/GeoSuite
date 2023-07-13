**********************
Introduction
**********************

.. contents:: Table of Contents

About 
==================

MapStore2 is produced by GeoSolutions (https://www.geosolutionsgroup.com/)

The full documenation is available at:  https://docs.geonode.org/

Documentation for MapStore2 is excellent and extensive.

The following tutorials are inteded only to get you up and running with a map, dashboard, and basic user management.


Location
==========

MapStore2 is deployed as a WAR file in Tomcat at:

/home/tomcat/apache-tomcat-version/webapps/mapstore


Remove
==========

If you do not wish to use MapStore2 for any reason, it can be removed following below.

First, stop Apache Tomcat.

Next, remove the MapStore2 webapp from Apache Tomcat using below

.. code-block:: bash

     root@demo:~# cd /home/tomcat/apache-tomcat-version/webapps
     root@demo:/webapps# rm -f mapstore.war
     root@demo:/webapps# rm -rf mapstore


Next, drop the geostore database used by MapStore2

.. code-block:: sql

     root@demo:~# su - postgres
     postgres=# drop database geostore;


Finally, disable the Apache proxy for MapStore2:

.. code-block:: bash

     root@demo:~# a2disconf mapstore.conf
     root@demo:~# service apache2 restart




 


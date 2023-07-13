**********************
Overview
**********************

.. contents:: Table of Contents

Version
==================

GeoSuite installs the latest, stable version of GeoServer.


Control Panel
=============

Within the control panel, GeoServer can be accessed via Servers > GeoServer

.. image:: _static/geoserver-panel-1.png

The GeoServer tab checks that GeoServer is installed.

.. image:: _static/geoserver-tab.png

If not, it can be installed using the "Install Now" button.

This will install the latest, stable version of GeoServer.


Edit Config
---------------
.. image:: _static/config-tab.png

The Config tab is used to edit the following files

.. code-block:: console

      server.xml
      web.xml
      context.xml
      tomcat-users.xml
      setenv.sh
   
These files can, of course, be edited via the file system or VI as well.


Apps
---------------
.. image:: _static/apps-tab.png

The Apps manager allows you to deploy, undeploy, and redeploy WAR files in Apache Tomcat

   
Java
---------------
.. image:: _static/java-tab.png

The Java tab is used during installation as well as for updating of JDK.

It can also be used to un-install the selected JDK and replace it with a new version.


.. image:: _static/java-installed.png


.. note::
    When installing or removing, there is an option to set as System default.



Location
================== 

By default, GeoServer is installed at /home/tomcat/apache-tomcat-<version>/webapps/geoserver

To make upgrading easier, you should always change the GeoServer Data Directory location.

To install GeoServer extensions, see our guide

As we can see above, the creation of our NewReports Directory has been added to the directory structure.  This is true for all directories and sub directories added.

Geoserver Extensions
====================

GeoServer Extensions can be installed as below.

Below, we are installing the MapFish Print Module via SSH.

**1. Switch to user tomcat**

.. code-block:: console
  

   su - tomcat
   

**2. Change to the GeoServer /lib directory (adjust for your own version of Tomcat)**

.. code-block:: console


   cd /home/tomcat/apache-tomcat-9.0.70/webapps/geoserver/WEB-INF/lib
   

**3. Download the desired extension, making sure to match the version to your GeoServer version**

.. code-block:: console


   wget http://sourceforge.net/projects/geoserver/files/GeoServer/2.23.1/extensions/geoserver-2.23.1-printing-plugin.zip


**4. Unzip the downloaded file**

.. code-block:: console


   unzip -q geoserver-2.23.1-printing-plugin.zip


**5. Remove the zip file**

.. code-block:: console


   rm -f geoserver-2.23.1-printing-plugin.zip

**6. Restart Tomcat for the extension to take effect.**

.. Note:: Some components, such as GDAL, require additional configuration. 


Data Directory
==============

To make GeoServer more portable and easier to upgrade, you should change the GeoServer data directory.

Follow the instructions below, substituting your own paths and file names.

**1. Stop Tomcat**

**2. Connect via SSH and move the data directory as below: (Important: the target directory - 'geodata' below - should not exist.)**

.. code-block:: console


   mv /home/tomcat/apache-tomcat-9.0.70/webapps/geoserver/data/ /var/lib/geodata/ 

   chown -R tomcat:tomcat /var/lib/geodata/ 

**3. Add the following to your GeoServer web.xml file:**

.. code-block:: xml


   <context-param>
       <param-name>GEOSERVER_DATA_DIR</param-name>
       <param-value>/var/lib/geodata</param-value>
   </context-param>
 
   <context-param>
      <param-name>GEOSERVER_REQUIRE_FILE</param-name>
      <param-value>/var/lib/geodata/global.xml</param-value>
   </context-param>   

**4. Start Tomcat**

You should log into GeoServer and verify that your workspaces, etc.. are accesible.


CSRF Whitelist
==============

GeoServer includes CSRF Protection to protect against form submission that did not originate from your GeoServer instance.

Follow the instructions below, substituting your own paths and file names.

**1. Stop Tomcat**

**2. Connect via SSH and move the geoserver WEB-INF directory**

**3. Add the following after the last context-param entry, subsituting your own domain for the param value**

.. code-block:: xml


   <context-param>
     <param-name>GEOSERVER_CSRF_WHITELIST</param-name>
     <param-value>YOURDOMAIN.COM</param-value>
   </context-param>

**4. Save the file and restart Tomcat for change to take effect**


Enable CORS
==============

To enable Cross-Origin Resource Sharing (CORS) it's best to do so using the Tomcat web.xml configuration file.

Follow the instructions below, substituting your own paths and file names.

**1. Stop Tomcat**

**2. Connect via SSH and move the Tomcat WEB-INF directory**

**3. Add the following above the closing <//web-app> tag**

.. code-block:: xml
   

   <filter>
     <filter-name>CorsFilter</filter-name>
     <filter-class>org.apache.catalina.filters.CorsFilter</filter-class>
     <init-param>
       <param-name>cors.allowed.origins</param-name>
       <param-value>*</param-value>
     </init-param>
   <init-param>
     <param-name>cors.allowed.methods</param-name>
     <param-value>GET,POST,HEAD,OPTIONS,PUT</param-value>
   </init-param>  
   </filter>
   <filter-mapping>
     <filter-name>CorsFilter</filter-name>
     <url-pattern>/*</url-pattern>
   </filter-mapping>

Important:  Above is a permissive CORS configuration.  You should adjust to suit your needs and requirements.



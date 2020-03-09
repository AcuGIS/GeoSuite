.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

**********************
General
**********************

.. contents:: Table of Contents
Edit Config
=============

.. image:: _static/config-tab.png

The Config tab is used to edit the following files::

   application.properties
   server.xml
   web.xml
   context.xml
   tomcat-users.xml
   setenv.sh
   
These files can, of course, be edited via the file system or VI as well.


Apps
====
.. image:: _static/apps-tab.png

The Apps manager allows you to deploy, undeploy, and redeploy WAR files in Apache Tomcat

   
Libraries
=========
.. image:: _static/libs-tab.png

The Libraries tab is for managing Jar files under::

   /home/tomcat/<tomcat-version>/libs

These files can, of course, via the file system.

.. note::
   The advantage of the Libraries tab is that it tracks Jar files installed,  This allows you to easily roll back changes.


Java
=========
.. image:: _static/java-tab.png

The Java tab is used during installation as well as for updating of JDK.

It can also be used to un-install the selected JDK and replace it with a new version.


.. image:: _static/java-installed.png


.. note::
    When installing or removing, there is an option to set as System default.


Proxy
=========

By default, JRI Publisher enables a Proxy for Apache and maps port 8080 to 80.

This enables you to reach reports via http://yourdomain.com/JasperReportsIntegration without need to add the 8080 port.

.. warning::

   While the proxy is convenient for development or intranet use, in production you should remove the proxy.  To remove the Proxy, follow the steps below:
   
To edit, remove, or update Proxies, click on the Proxy tab as shown below.

.. image:: _static/proxy.png

Select the default Proxy of / as shown below, and click Remove.

.. image:: _static/proxy-remove.png


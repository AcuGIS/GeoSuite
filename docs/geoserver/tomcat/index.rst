.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

**********************
Apache Tomcat
**********************

.. contents:: Table of Contents

Layout
======

The Apache Tomcat (CATALINA) home directory is::

   /home/tomcat/apache-tomcat-v/
   
Where apache-tomcat-v is the version installed.

The CATALINA_HOME variable is set both in the Tomcat init script as well as setenv.sh files.


Starting and Stopping
=====================

There are two ways to start/stop/restart Tomcat.

1.  Via Module, using the Stop/Start/Restart buttons as shown below::

   .. image:: _static/tomcat-functions.png

2.  Via SSH, using the following commands

.. code-block:: console

    systemctl { start | stop | restart | status } tomcat

3.  If Tomcat is not responding, you can also issue, as root

.. code-block:: console

	pkill -9 java
    

Service
===========

Tomcat runs as a system service.

The service file is located at /etc/systemd/system/tomcat.service

The content of the tomcat.service file are below:

.. code-block:: bash  
	
	[Unit]
	Description=Tomcat ${TOMCAT_VER}
	After=multi-user.target

	[Service]
	User=tomcat
	Group=tomcat

	WorkingDirectory=${CATALINA_HOME}
	Type=forking
	Restart=always

	EnvironmentFile=/etc/environment

	ExecStart=$CATALINA_HOME/bin/startup.sh
	ExecStop=$CATALINA_HOME/bin/shutdown.sh 60 -force

	[Install]
	WantedBy=multi-user.target

Any changes to the system file should be followed by

.. code-block:: bash 

	systemctl daemon-reload

Version
=======

By default, Tomcat 9.x latest is used

Tomcat Users
=================

On installation, random passwords are generated for both the admin and manager roles.

The passwords can be found at  /home/tomcat/apache-tomcat-version/conf/tomcat-users.xml


.. code-block:: xml 

	<?xml version='1.0' encoding='utf-8'?>
	<tomcat-users>
	<role rolename="manager-gui" />
	<user username="manager" password="aupiZ0GlzHAaC5-8GgL2gAi7XNuEiTE0" roles="manager-gui" />

	<role rolename="admin-gui" />
	<user username="admin" password="67Jyz1EdDXmmFfOL9DOBFzuwr17MUgLa" roles="manager-gui,admin-gui" />
	</tomcat-users>


setenv.sh
==============

The setenv.sh file is located at /home/tomcat/apache-tomcat-version/bin/setenv.sh

The default parameters set by GeoSuite are:

.. code-block:: java 

	CATALINA_PID="/home/tomcat/apache-tomcat-9.0.76/temp/tomcat.pid"
	JAVA_OPTS="${JAVA_OPTS} -server -Djava.awt.headless=true -Dorg.geotools.shapefile.datetime=false -XX:+UseParallelGC -XX:ParallelGCThreads=4 -Dfile.encoding=UTF8 -Duser.timezone=UTC -Djavax.servlet.request.encoding=UTF-8 -Djavax.servlet.response.encoding=UTF-8 -DGEOSERVER_CSRF_DISABLED=true -DPRINT_BASE_URL=http://localhost:8080/geoserver/pdf -Dgwc.context.suffix=gwc"
	JAVA_OPTS="$JAVA_OPTS -Dgeostore-ovr=file:///home/tomcat/apache-tomcat-9.0.76/conf/geostore-datasource.properties"








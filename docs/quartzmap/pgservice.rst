*************************
The pg_service.conf File
*************************

Throughout the documentation, we reference pg_service.conf files.  If you are not already familiar with the pg_service.conf file, be sure to read below.


.. contents:: Table of Contents


About the pg_service.conf File
====================================

The pg_service.conf file is used to connect your QGIS project to a PostGIS database using only a Service Name.

It is important to use a pg_service.conf file rather than storing/saving your password inside of your QGIS Project file.


Contents of the pg_service.conf File
===================================

The form of the pg_service.conf file is like below.  The Service Name is in brackets ([beeedatabse] below)

The pg_service.conf file can have multiple entries.

.. code-block:: console

  [beedatabase]
  host=localhost
  port=5432
  dbname=beedatabase
  user=admin1
  password=DfBJdQTtQv

When you add a Data Source in your control panel, it automatically adds an entry to the pg_service.conf file located at:

.. code-block:: console

  /var/www/data/qgis/pg_service.conf

If you connect your QGIS Project to your PostGIS database, the same pg_service.conf entry is used on your local computer, but with the hostname pointed at your server. 

For example, if our QuartzMap server is located at https://myserver.com, our hostname would be as below:

.. code-block:: console

  [beedatabase]
  host=myserver.com
  port=5432
  dbname=beedatabase
  user=admin1
  password=DfBJdQTtQv


Video Tutorial
===================================

For a complete walkthrough of pg_service.conf files, see our video below.

.. note::
    The video below is for Lizmap, but the same procedures apply to QuartzMap.





.. raw:: html

    <iframe width="1125" height="703" src="https://www.youtube.com/embed/5iT9VDKTxDM" title="Lizmap - The pg_service.conf file" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>

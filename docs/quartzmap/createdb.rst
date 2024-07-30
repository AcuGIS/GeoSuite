**********************
Create Database
**********************

If you do not have an existing PostGIS database, you can create one from your QGIS Project.

You have two options for doing so.

.. contents:: Table of Contents


Option 1: Export layers to GeoPackage
===================================


1. Export your layer(s)
------------------------

Right click on layer > Export > Save As > GeoPackage

  .. image:: images/create-db-1.png



2. Upload GeoPackages
-------------------------

Go to Data Sources > Create and upload your GeoPackage(s).

  .. image:: images/create-db-2.png


3. Set Layers to Data Source
-------------------------------

Set your map layer(s) to use your new Data Source

 .. image:: images/select-data.png

 
4. Change QGIS Data Source
-------------------------------

Optionally, you can now also set your QGIS Project to use your new database as well.

Just right click on the layer(s) > Change Data Source

Select the PostGIS data source you created above.


 .. image:: images/create-db-3.png



Option 2: Create Empty DB and Use DB Manager
===================================


1. Create an empty database
------------------------

Go to Data Sources > QGS > Create

Be sure to select "Only Create PostGIS Database"

Give your database a name and click Create

  .. image:: images/create-empty-db-1.png


2. Add pg_service.conf Entry
-------------------------

Go to Data Sources and click "Show Connection Information" for your new database

  .. image:: images/create-empty-db-4.png

Add the connection information to your local pg_service.conf file

  .. image:: images/db-manager-3.png


Add the new connection to QGIS PostgreSQL


3. Create Tables from Layers
-------------------------------

In QGIS, go to DB Manager, select your database and click "Import Layer/File"

 .. image:: images/create-empty-db-5.png


Select the layer to input and table name:

 .. image:: images/create-empty-db-6.png

Repeat for any additional layers.
 

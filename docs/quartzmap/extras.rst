Enable geolocation
==================

The automatic geolocation provided by Lizmap relies on Google services. To enable it, your webGIS must be placed under a secure protocol, like HTTPS. See for more details:

https://sites.google.com/a/chromium.org/dev/Home/chromium-security/deprecating-powerful-features-on-insecure-origins

https://www.digitalocean.com/community/tutorials/how-to-create-a-self-signed-ssl-certificate-for-apache-in-ubuntu-16-04

.. _install-data-folder:

Create directories for data
===========================

QGIS files and other cache files will be stored into these directories.

.. code-block:: bash

   mkdir /home/data
   mkdir /home/data/cache/

Spatial Database: PostgreSQL
============================

.. note:: This section is optional. Please read :ref:`prerequisites-postgresql`.

PostgreSQL and PostGIS can be very useful to manage spatial data centralized manner on the server.

Install
-------


On Debian 11, you'll find PostgreSQL 13.

First install packages:

.. code-block:: bash

   apt install postgresql postgresql-contrib postgis pgtune


You may have to recreate the cluster on a fresh install, in order to set the locale.
You can jump this step if the locale is correctly set, or if you already have
databases. Careful: these instructions destroy any existing databases!

.. code-block:: bash

   service postgresql stop
   pg_dropcluster --stop 13 main
   pg_createcluster 13 main --locale fr_FR.UTF8 -p 5432 --start

Now You can create a user and a database for Lizmap, into Postgresql.


Adapting the PostgreSQL configuration
-------------------------------------

We will use ``pgtune``, an utility program that can automatically generate a PostgreSQL configuration file
adapted to the properties of the server (memory, processors, etc.)

https://pgtune.leopard.in.ua/

.. code-block:: bash

   # PostgreSQL Tuning with pgtune
   pgtune -i /etc/postgresql/13/main/postgresql.conf -o /etc/postgresql/13/main/postgresql.conf.pgtune --type Web
   cp /etc/postgresql/13/main/postgresql.conf /etc/postgresql/13/main/postgresql.conf.backup
   cp /etc/postgresql/13/main/postgresql.conf.pgtune /etc/postgresql/13/main/postgresql.conf
   nano /etc/postgresql/13/main/postgresql.conf
   # Restart to check any problems
   service postgresql restart
   # If error messages, increase the linux kernel configuration variables
   echo "kernel.shmall = 4294967296" >> /etc/sysctl.conf # to increase shred buffer param in kernel
   echo "kernel.shmmax = 4294967296" >> /etc/sysctl.conf
   echo 4294967296 > /proc/sys/kernel/shmall
   echo 4294967296 > /proc/sys/kernel/shmmax
   sysctl -a | sort | grep shm
   # Restart PostgreSQL
   service postgresql restart

For installing Lizmap tables into the PostgreSQL database (instead of SqLite by default), you can continue until the next section
below when you need to edit the file :file:`lizmap/var/config/profiles.ini.php`.

Installing sources of Lizmap Web Client
=======================================

Retrieve the latest available stable version from our `Github release page <https://github.com/3liz/lizmap-web-client/releases/>`_.

.. warning::
    Do not use the automatic ZIP file created by GitHub on the website. Only use ZIP attached to a release.

We first set some variable to ease instructions. Let's set the version and
the location where Lizmap will be installed. Adjust these values to your
requirements.

.. code-block:: bash

   VERSION=3.6.5
   LOCATION=/var/www

Then you can install the zip file:

.. code-block:: bash

   cd $LOCATION
   wget https://github.com/3liz/lizmap-web-client/releases/download/$VERSION/lizmap-web-client-$VERSION.zip
   # Unzip archive
   unzip lizmap-web-client-$VERSION.zip

   # virtual link for http://localhost/lizmap/
   ln -s $LOCATION/lizmap-web-client-$VERSION/lizmap/www/ /var/www/html/lizmap
   # Remove archive
   rm lizmap-web-client-$VERSION.zip


Configure Lizmap with the database support
==========================================

Lizmap needs a database to store its own data and to access to data used in your
Qgis projects, with its editing tool.

Create :file:`profiles.ini.php` into :file:`lizmap/var/config` by copying :file:`profiles.ini.php.dist`.

.. code-block:: bash

   cd lizmap/var/config
   cp profiles.ini.php.dist profiles.ini.php
   cd ../../..

PostgreSQL
----------

For the editing of PostGIS layers in Web Client Lizmap operate, install PostgreSQL support for PHP. No configuration file need to be edited
to edit PostgreSQL layer. You must **only** check that the Lizmap server can access the database with credentials which are stored in the QGIS project
(or with a PostgreSQL service file).

.. code-block:: bash

   apt install php7.4-pgsql
   service php7.4-fpm restart

For Lizmap logs, users and groups, it can be either stored in SqLite or PostgreSQL. To store these information in
PostgreSQL, follow these instructions.

Into a fresh copy of :file:`lizmap/var/config/profiles.ini.php`, you should have:

.. code-block:: ini

    [jdb:jauth]
    driver=sqlite3
    database="var:db/jauth.db"

    [jdb:lizlog]
    driver=sqlite3
    database="var:db/logs.db"

This is the configuration by default to use Sqlite. You should change these
sections to use Postgresql, and indicate several parameters to access to your
Postgresql database:

.. code-block:: ini

    [jdb:jauth]
    driver=pgsql
    host=localhost
    port=5432
    database="your_database"
    user=my_login
    password=my_password
    search_path=public

    [jdb:lizlog]
    driver=pgsql
    host=localhost
    port=5432
    database="your_database"
    user=my_login
    password=my_password
    search_path=public


You can use a specific schema to store lizmap tables. And you may want that lizmap
could access to other schema. You then have to set search_path correctly. Example:

.. code-block:: ini

    search_path=lizmap,my_schema,public

If you have setup a service file for postgresql onto your server, you may want to
indicate a postgresql service instead of indicating login, password and so on.
Use then the service parameter:

.. code-block:: ini

    [jdb:jauth]
    driver=pgsql
    service=my_service
    database="your_database"
    search_path=lizmap,public

    [jdb:lizlog]
    driver=pgsql
    service=my_service
    database="your_database"
    search_path=lizmap,public

Spatialite
----------

Enable Spatialite extension
^^^^^^^^^^^^^^^^^^^^^^^^^^^

To use editing on layers spatialite,you have to add the spatialite extension in PHP. You can follow these instructions to do so:
http://www.gaia-gis.it/gaia-sins/spatialite-cookbook-fr/html/php.html

Lizmap Web Client tests whether the spatialite support is enabled in PHP. If it is not, then spatialite layers will not be used in the editing tool. You can always use PostgreSQL data for editing.

Give the appropriate rights to the directory containing Spatialite databases
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

So that Lizmap Web Client can modify the data contained in databases Spatialite, we must ensure that **the webserver user (www-data) has well write access to the directory containing each Spatialite file**

For example, if a directory contains a QGIS project, which uses a Spatialite database placed in a **db** directory at the same level as the QGIS project:

.. code-block:: bash

   /path/to/a/lizmap_directory
   |--- mon_projet.qgs
   |--- bdd
      |--- my_spatialite_file.sqlite

So you have to give the rights in this way:

.. code-block:: bash

   chown :www-data /path/to/a/lizmap_directory -R
   chmod 775 /path/to/a/lizmap_directory -R

.. note::
    So if you want to install Lizmap to provide access to multiple map publishers, you should tell them to
    always create a **db** directory at the same level as the QGIS projects in the Lizmap Web Client directory.
    This will facilitate the admin work that just have to change the rights of this unique directory.



Configuring Lizmap and launching the installer
================================================

Give the appropriate rights to directories and files
--------------------------------------------------------------

Set rights for Nginx/Apache, so PHP scripts could write some temporary files or do changes.

.. code-block:: bash

   cd /var/www/lizmap-web-client-$VERSION/
   lizmap/install/set_rights.sh www-data www-data


Setup configuration
-------------------


Create :file:`lizmapConfig.ini.php`, :file:`localconfig.ini.php` and edit them
to set parameters specific to your installation. You can modify :file:`lizmapConfig.ini.php`
to set the url of qgis map server and other things.

.. code-block:: bash

   cd lizmap/var/config
   cp lizmapConfig.ini.php.dist lizmapConfig.ini.php
   cp localconfig.ini.php.dist localconfig.ini.php
   cd ../../..

Launching the installer
-----------------------

After creating configuration files, you can launch the installer

.. code-block:: bash

   php lizmap/install/installer.php

It will finished the installation, and will create all SQL tables needed by Lizmap.

Adding some demonstration projects
----------------------------------

If you want to test Lizmap with some demonstration projects, you must install ``unzip`` and either ``wget`` or ``curl``.

.. code-block:: bash

    lizmap/install/reset.sh --keep-config --demo

First test
----------

For testing launch: ``http://localhost/lizmap`` in your browser.

In case you get a ``500 - internal server error``, run again:

.. code-block:: bash

   cd /var/www/lizmap-web-client-$VERSION/
   lizmap/install/set_rights.sh www-data www-data


.. note:: Replace ``localhost`` with the address or IP number of your server.

In the administration panel, you should check the :guilabel:`QGIS server version` and the :guilabel:`WMS server URL` with the URL of QGIS Server.

.. warning::
    Before trying to have a QGIS project working in Lizmap, you **must** have the communication between QGIS Server and Lizmap Web Client working properly.
    Versions about QGIS Server plugins **must** be visible from the administration interface. Please read :ref:`lizmap-server-plugin`.

If you didn't install the demo, you can check that you have well installed Lizmap and configured QGIS Server within Lizmap by checking the ``qgis_server`` section in this URL:
http://localhost/lizmap/index.php/view/app/metadata

.. code-block:: json

    {
        "qgis_server":{
            "test":"OK",
            "mime_type":"text\/xml; charset=utf-8"
        }
    }

Lizmap is accessible, without further configurations, also as WMS and WFS server from a browser:

http://localhost/lizmap/index.php/lizmap/service/?repository=montpellier&project=montpellier&VERSION=1.3.0&SERVICE=WMS&REQUEST=GetCapabilities

http://localhost/lizmap/index.php/lizmap/service/?repository=montpellier&project=montpellier&SERVICE=WFS&REQUEST=GetCapabilities

and from QGIS:

http://localhost/lizmap/index.php/lizmap/service/?repository=montpellier&project=montpellier&VERSION=1.3.0&

http://localhost/lizmap/index.php/lizmap/service/?repository=montpellier&project=montpellier&

.. note::
    Access to the WMS and WFS servers can be limited by assigning privileges to specific repositories, see
    the administration section.

Lizmap modules
==============

Previously, we explained how we could add QGIS Server plugins to add more features to QGIS Server. Now that
we have Lizmap Web Client up and running, we can add some Lizmap modules to add again some features.

The list is available in the Lizmap :ref:`introduction<additional_lizmap_modules>`. On their GitHub repository,
their is usually their install instructions. You should follow them. However
here are the main instructions to install a module.


Installing modules with Composer
--------------------------------

You can install modules with Composer, the package manager for
PHP. Of course it is possible only if the author of the module has created
a package of his module. A such package has a name, for example `lizmap/lizmap-pgmetadata-module``.
The documentation of the module should indicate it.

You must install Composer. See instructions on its web site http://getcomposer.org.

You must create a :file:`composer.json` file into :file:`lizmap/my-packages/`
by copying the :file:`composer.json.dist` from this directory. And launching
a first time Compose


.. code-block:: bash

    cp -n lizmap/my-packages/composer.json.dist lizmap/my-packages/composer.json
    composer install --working-dir=lizmap/my-packages


Then you can install the package of the module

.. code-block:: bash

    composer require --working-dir=lizmap/my-packages "lizmap/lizmap-pgmetadata-module"


If you want to install a new version of the module, execute:

.. code-block:: bash

    composer update --working-dir=lizmap/my-packages

Read the documentation of the module to know if there are additional steps to
configure it.

You will have at least to launch the configurator of the module with this command:

.. code-block:: bash

    php lizmap/install/configurator.php name_of_the_module
    # example:
    php lizmap/install/configurator.php pgmetadata

To finish the installation, run again the installer of Lizmap:

.. code-block:: bash

    php lizmap/install/installer.php
    lizmap/install/clean_vartmp.sh
    lizmap/install/set_rights.sh


installing modules without Composer
-----------------------------------

To install a module without Composer, retrieve the zip file of the module.

* Extract the module into :file:`lizmap/lizmap-modules/`. For instance, for the module
  ``PgMetadata`` :

.. code-block:: bash

    $ ls -hl lizmap/lizmap-modules/pgmetadata/
    total 44K
    drwxrwxr-x 2 etienne etienne 4,0K nov.  17 12:38 classes
    drwxrwxr-x 2 etienne etienne 4,0K nov.   4 12:50 controllers
    drwxrwxr-x 2 etienne etienne 4,0K nov.   4 10:09 daos
    -rw-rw-r-- 1 etienne etienne  146 nov.   4 10:38 events.xml
    drwxrwxr-x 2 etienne etienne 4,0K nov.   4 10:09 forms
    drwxrwxr-x 2 etienne etienne 4,0K nov.   4 12:50 install
    drwxrwxr-x 4 etienne etienne 4,0K nov.   4 10:09 locales
    -rw-rw-r-- 1 etienne etienne  789 nov.  19 16:02 module.xml
    drwxrwxr-x 2 etienne etienne 4,0K nov.   4 10:09 templates
    -rw-rw-r-- 1 etienne etienne  106 nov.   4 10:39 urls.xml
    drwxrwxr-x 2 etienne etienne 4,0K nov.  17 12:38 www


* Read the documentation of the module to know if there are additional steps to
  configure it.

You will have at least to launch the configurator of the module with this command:

.. code-block:: bash

    php lizmap/install/configurator.php name_of_the_module
    # example:
    php lizmap/install/configurator.php pgmetadata


* Run the installation :

.. code-block:: bash

    php lizmap/install/installer.php
    lizmap/install/clean_vartmp.sh
    lizmap/install/set_rights.sh



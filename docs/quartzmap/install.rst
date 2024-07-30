Install
=====

System Requirements
------------------

    - Operating System:
        - Ubuntu 22 or 24

    - Minimum:
        - 1 GB RAM
        - 10 GB Disk
        - 1 vCPU

    - Recommended:
        - 4 GB RAM
        - 25 GB Disk
        - 2 vCPU


Using the Script Installers
-------------------------

If you plan to use SSL, be sure to set the hostname

.. code-block:: console

	$ hostnamectl set-hostname qpod.webgis1.com
	

Download QuartzMap or use Git.

Installer scripts are available in the install directory and should be called from the quartzmap directory.

.. code-block:: console

    $ git clone https://github.com/AcuGIS/quartzmap.git
    $ cd quartzmap

If PostgreSQL is not installed, install it using /installer/postgres.sh

.. code-block:: console

    $ ./installer/postgres.sh


Run the installer:

.. code-block:: console

    $ ./installer/app-install.sh

Optionally, install an SSL certificate using certbot

.. code-block:: console

    certbot --apache --agree-tos --email hostmaster@yourdomain.com --no-eff-email -d yourdomain.com


Go to https://yourdomain.com/admin/setup.php to complete the installation

If checks pass, click "Next"


  .. image:: images/install-1.png


Populate the required fields and click Submit

  .. image:: images/install-2.png


Log in to the application:


 .. image:: images/install-3.png





Manual Installation
-------------------

The installation steps below are from the app-install.sh script. You can adapt as needed.

For below, we will use the following. 

.. code-block:: console

    APP_DB='quartz'
    APP_DB_PASS='SuperSecret';
    DATA_DIR='/var/www/data'
    CACHE_DIR='/var/www/cache'
    APPS_DIR='/var/www/html/apps'


Install dependencies:

.. code-block:: console

    apt-get -y install apache2 php-{pgsql,zip,gd,simplexml,curl,fpm} proftpd libapache2-mod-fcgid postfix python3-certbot-apache gdal-bin

Install QGIS Repository

.. code-block:: console

	wget --no-check-certificate --quiet -O /etc/apt/keyrings/qgis-archive-keyring.gpg https://download.qgis.org/downloads/qgis-archive-keyring.gpg


Check release using  lsb_release -cs and create /etc/apt/sources.list.d/qgis.sources as below

.. code-block:: console

    Types: deb deb-src
    URIs: https://qgis.org/ubuntu
    Suites: # use output from lsb_release -cs above
    Architectures: amd64
    Components: main
    Signed-By: /etc/apt/keyrings/qgis-archive-keyring.gpg


Update and install QGIS Server:

.. code-block:: console  

	apt-get update -y || true
    
    apt-get install -y qgis-server


Create /etc/logrotate.d/qgisserver with below:

.. code-block:: console  

	
	/var/log/qgisserver.log {
	su www-data www-data
	size 100M
	notifempty
	missingok
	rotate 3
	daily
	compress
	create 660 www-data www-data

 Create qgisserver.log and set permissions

.. code-block:: console     

	
	mkdir -p ${DATA_DIR}/qgis
	chown www-data:www-data ${DATA_DIR}/qgis
	
	touch /var/log/qgisserver.log
	chown www-data:www-data /var/log/qgisserver.log

Set up Apache

.. code-block:: console

    a2enmod ssl headers expires fcgid cgi
    

Copy conf files from installer directory

.. code-block:: console

    cp installer/apache2.conf /etc/apache2/sites-available/default-ssl.conf


Copy conf files from installer directory and configure

.. code-block:: console


    sed "s|\$DATA_DIR|$DATA_DIR|" < installer/qgis_apache2.conf > /etc/apache2/sites-available/qgis.conf

    a2ensite 000-default default-ssl qgis
    a2disconf serve-cgi-bin


Switch to mpm_event and use HTTP2

.. code-block:: console

    a2enmod proxy_fcgi setenvif http2
    a2enconf php8.1-fpm
    a2enmod mpm_event

    systemctl reload apache2

Set up ProFTPD

.. code-block:: console

    sed -i.save '
    s/#DefaultRoot~/DefaultRoot ~/
    s/# RequireValidShelloff/RequireValidShell off/' /etc/proftpd/proftpd.conf
    systemctl enable proftpd
    systemctl restart proftpd

Create the PostgreSQL database

.. code-block:: console

    
    su postgres
    createdb quartz
    createuser -sd quartz
    psql -c "alter user quartz with password 'SuperSecret'"
    psql -c "ALTER DATABASE quartz OWNER TO quartz"
    CMD_EOF

Create the Data, Cache, and Apps directories

.. code-block:: console

    mkdir -p "${APPS_DIR}"
    mkdir -p "${CACHE_DIR}"
    mkdir -p "${DATA_DIR}"


    chown -R www-data:www-data "${APPS_DIR}"
    chown -R www-data:www-data "${CACHE_DIR}"
    chown -R www-data:www-data "${DATA_DIR}"



Give sync service +w to apps/1/images dir

.. code-block:: console

    chmod -R g+w "${APPS_DIR}"

Create the admin/incl/const.php configuration file using values from above

.. code-block:: console

    <?php
    define("DB_HOST", "localhost");
    define("DB_NAME", "${APP_DB}");
    define("DB_USER", "${APP_DB}");
    define("DB_PASS", "${APP_DB_PASS}");
    define("DB_PORT", 5432);
    define("DB_SCMA", 'public');
    define("APPS_DIR", "${APPS_DIR}");
    define("CACHE_DIR", "${CACHE_DIR}");
    define("DATA_DIR", "${DATA_DIR}");
    define("SUPER_ADMIN_ID", 1);
    define("SESS_USR_KEY", 'quartz_user');
    ?>    

Copy files from quartzmap directory to /var/www/html

.. code-block:: console

    cp -r . /var/www/html/
    chown -R www-data:www-data /var/www/html
    rm -rf /var/www/html/installer

    systemctl restart apache2

11. Configure FTP users

.. code-block:: console

    groupadd qatusers

    for f in create_ftp_user delete_ftp_user update_ftp_user; do
	    cp installer/${f}.sh /usr/local/bin/
	    chown www-data:www-data /usr/local/bin/${f}.sh
	    chmod 0550 /usr/local/bin/${f}.sh
    done

    cat >/etc/sudoers.d/q2w <<CAT_EOF
    www-data ALL = NOPASSWD: /usr/local/bin/create_ftp_user.sh, /usr/local/bin/delete_ftp_user.sh, /usr/local/bin/update_ftp_user.sh
    CAT_EOF


Optionally, install an SSL certificate using certbot

.. code-block:: console

    certbot --apache --agree-tos --email hostmaster@yourdomain.com --no-eff-email -d yourdomain.com


Go to https://yourdomain.com/admin/setup.php to complete the installation
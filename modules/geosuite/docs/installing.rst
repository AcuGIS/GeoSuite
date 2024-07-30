************
Installation
************

Installation can be done using the pre-installer.sh script or via GIT.

Using the Pre-Installer
=======================

On a fresh CentOS 7 or Ubuntu 18 installation, the fastest method is to use the pre-installer script:

.. code-block:: console
   :linenos:
   
   wget https://raw.githubusercontent.com/AcuGIS/GeoSuite/master/scripts/pre-install.sh
   chmod +x pre-install.sh
   ./pre-install.sh
    
The above will install Webmin, Apache HTTPD Server, GeoSuite module, as well as our (optional) Certbot Module for SSL.

When the script completes, you will see the message below:

.. code-block:: console
   :linenos:

   ~
   /opt ~
   Installed CertBot in /usr/share/webmin/certbot (336 kb)
   ~
   GeoSuite is now installed. Go to Servers > GeoSuite to complete installation


.. note::
    Following above, you will need to log in to Webmin to complete installation using the install :ref:`wizard-label`.



Via Git or Download
===================

You can use Git to build module for an existing Webmin installation:

.. code-block:: console
   :linenos:

    git clone https://github.com/AcuGIS/GeoSuite
    mv GeoSuite-Master geosuite
    tar -cvzf geosuite.wbm.gz geosuite/

    
.. note::
    Following above, you will need to log in to Webmin to complete installation using the install :ref:`wizard-label`.   
    


